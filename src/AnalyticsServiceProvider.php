<?php

declare(strict_types=1);

namespace Falcon\Analytics;

use Falcon\Analytics\Console\CheckCommand;
use Falcon\Analytics\Console\CheckEventsCommand;
use Falcon\Analytics\Console\GeoipCheckCommand;
use Falcon\Analytics\Console\GeoipDownloadCommand;
use Falcon\Analytics\Console\InstallCommand;
use Falcon\Analytics\Console\PruneCommand;
use Falcon\Analytics\Console\ScanEventsCommand;
use Falcon\Analytics\Console\SweepCommand;
use Falcon\Analytics\Console\SyncSearchConsoleCommand;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Support\GeoResolver;
use Falcon\Ui\AssetRegistry;
use Falcon\Ui\Config\CompletesDefaults;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class AnalyticsServiceProvider extends ServiceProvider
{
    use CompletesDefaults;

    public function register(): void
    {
        // `completeConfigFrom` et non `mergeConfigFrom` · celui de Laravel ne
        // complete que le premier niveau. Une copie publiee qui vieillit perd
        // donc toute sous-cle ajoutee depuis, sans un mot. Le kit porte cette
        // politique pour toute la suite ; on ne la recopie pas.
        $this->completeConfigFrom(__DIR__.'/../config/analytics.php', 'analytics');

        $this->app->singleton(Analytics::class);

        $this->app->singleton(GeoResolver::class, fn (): GeoResolver => new GeoResolver(
            self::configured('analytics.geoip.database_path'),
            self::configured('analytics.geoip.dev_ip'),
        ));

        $this->app->singleton(FunnelRegistry::class, function (): FunnelRegistry {
            $registry = new FunnelRegistry;
            $path = self::configured('analytics.funnels_path', base_path('app/Analytics/funnels.php'));

            if ($path !== null && is_file($path)) {
                $registry->load($path);
            }

            return $registry;
        });

        $this->app->singleton(EventRegistry::class, function (): EventRegistry {
            $registry = new EventRegistry;
            $path = self::configured('analytics.events_path', base_path('app/Analytics/events.php'));

            if ($path !== null && is_file($path)) {
                $registry->load($path);
            }

            return $registry;
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'analytics');

        // Un fichier par espace · l'administration et ses ecrans, le public et
        // son point d'ingestion. Les deux sont charges, toujours · un fichier
        // qu'on cesserait de charger serait du code mort qui a l'air vivant.
        $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Ou sont les fichiers compiles du paquet. Le kit lit ce registre pour
        // batir leur adresse, et pour comparer la copie publiee a la source ·
        // une copie plus ancienne leve, plutot que de servir en silence la
        // feuille du mois dernier.
        $this->app->make(AssetRegistry::class)->register('analytics', __DIR__.'/../public');

        $this->registerPersistentMiddleware();

        /*
         * Les composants, sous un seul prefixe et par deux mecanismes.
         *
         * Laravel cherche d'abord une classe, et retombe sur la vue anonyme
         * quand elle n'existe pas · `<x-analytics::page>` touche la classe,
         * `<x-analytics::kpi-card>` la vue.
         *
         * Page et racine sont des classes parce qu'elles ouvrent le contexte du
         * kit avant leur slot · une vue ne peut rien faire avant d'etre rendue.
         */
        Blade::componentNamespace('Falcon\\Analytics\\View\\Components', 'analytics');
        Blade::anonymousComponentNamespace('analytics::components', 'analytics');

        // The package's only directive, and it carries server data alone: the
        // current route's name, and tracking cut while an excluded guard is
        // authenticated. The collector's code is imported by the host in its
        // public entrypoint and compiled by its build.
        Blade::directive('analyticsConfig', fn (): string => '<?php echo \Falcon\Analytics\View\Collector::render(); ?>');

        /*
         * Les ecrans et leurs blocs, par espace de noms.
         *
         * Trente lignes d'enregistrement manuel vivaient ici, une par classe.
         * Un ecran ajoute sans sa ligne n'existait pas, et l'absence ne se
         * voyait qu'a l'affichage.
         *
         * Le nom se deduit maintenant du chemin de la classe · celle qui porte
         * la vue d'ensemble repond a `<livewire:analytics::admin.overview-page />`,
         * et un bloc range sous `Widgets` a `admin.widgets.trend-chart`.
         *
         * Livewire ne decouvre tout seul que `app/Livewire` · les classes d'un
         * paquet vivent ailleurs, d'ou cette declaration.
         */
        Livewire::addNamespace('analytics', classNamespace: 'Falcon\\Analytics\\Livewire');

        // Registered OUTSIDE any runningInConsole() guard on purpose: shared
        // hosts often trigger the scheduler through an HTTP endpoint calling
        // Artisan::call('schedule:run'), where runningInConsole() is false. A
        // console-only guard would silently unregister every command and every
        // schedule below in that setup. commands() only queues an
        // Artisan::starting callback, so ordinary HTTP requests pay nothing.
        $this->commands([
            InstallCommand::class,
            CheckCommand::class,
            GeoipDownloadCommand::class,
            GeoipCheckCommand::class,
            PruneCommand::class,
            SweepCommand::class,
            ScanEventsCommand::class,
            CheckEventsCommand::class,
            SyncSearchConsoleCommand::class,
        ]);

        // Self-schedule maintenance so a host only needs to trigger the
        // standard scheduler (real cron or HTTP-called schedule:run), never a
        // dedicated analytics cron. Lazily bound: the events register when the
        // Schedule is actually resolved, i.e. only inside scheduler runs.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // Close idle sessions and prune expired raw events on a fixed cadence.
            $schedule->command('analytics:sweep')->everyFiveMinutes()->withoutOverlapping();
            $schedule->command('analytics:prune')->dailyAt('03:30')->withoutOverlapping();

            // Refresh the GeoLite2 database monthly; inert until a licence key is set.
            $schedule->command('analytics:geoip:download')
                ->monthlyOn(1, '04:00')
                ->withoutOverlapping()
                ->when(fn (): bool => (string) config('analytics.geoip.license_key') !== '');

            // Pull the Search Console queries daily; the command is inert
            // while no connection is attached.
            $schedule->command('analytics:search-console:sync')
                ->dailyAt('05:00')
                ->withoutOverlapping();
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/analytics.php' => config_path('analytics.php'),
            ], 'analytics-config');

            /*
             * The views, for a host that wants to wrap a screen in a banner, a
             * breadcrumb or a container of its own. The thin views are the ones
             * that matter: the screen's body stays the package's, so it keeps
             * being updated.
             *
             * Publishing is never required, a view laid in
             * `resources/views/vendor/analytics/` being taken into account
             * anyway.
             */
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/analytics'),
            ], 'analytics-views');

            /*
             * The compiled files, under two names. `laravel-assets` is the one
             * a deployment forces in every release; `analytics-assets` is for
             * taking these and nothing else.
             *
             * The package compiles and ships compiled files; the host publishes
             * and serves them, with no Node and no build of its own.
             */
            $this->publishes([
                __DIR__.'/../public' => public_path($this->publicDirectory().'/analytics'),
            ], ['analytics-assets', 'laravel-assets']);
        }
    }

    /**
     * Where the application keeps the suite's published files.
     *
     * The kit owns this setting, and the package reads the same one: two
     * packages publishing into two different directories would leave the kit
     * building an address for one of them that points at the other's.
     */
    private function publicDirectory(): string
    {
        $configured = config('ui.assets.path', 'vendor/falcon');

        return is_string($configured) ? $configured : 'vendor/falcon';
    }

    /**
     * A configured string, or the fallback when the key says nothing.
     *
     * **A blank value counts as saying nothing**, and that is the whole point of
     * this method. An environment variable left empty in a `.env` reads back as
     * `''`, not as absent: `??` alone would take that empty string for an
     * answer and hand it on as a file path or a view name.
     *
     * A value that is not a string is treated the same way. A published
     * configuration that has drifted — an array where a path was expected —
     * falls back rather than travelling on to fail somewhere else.
     */
    private static function configured(string $key, ?string $fallback = null): ?string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    /**
     * Replay the host's administration middleware on every Livewire component
     * update (/livewire/update). Livewire only re-runs middleware registered as
     * persistent, so without this a signed component snapshot from a formerly
     * authorized session could keep triggering actions (GDPR erasure, campaign
     * CRUD) after the host middleware would deny the page. The 'web' stack is
     * excluded: Livewire already runs it on updates.
     */
    private function registerPersistentMiddleware(): void
    {
        $middleware = array_values(array_unique(array_filter(
            [
                ...(array) config('analytics.admin.middleware', []),
                ...(array) config('analytics.admin.marketing.middleware', []),
            ],
            fn (mixed $entry): bool => is_string($entry) && $entry !== '' && $entry !== 'web',
        )));

        if ($middleware !== []) {
            Livewire::addPersistentMiddleware($middleware);
        }
    }
}
