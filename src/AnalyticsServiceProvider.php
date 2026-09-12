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
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class AnalyticsServiceProvider extends ServiceProvider
{
    use CompletesDefaults;

    public function register(): void
    {
        // `completeConfigFrom` rather than `mergeConfigFrom`: Laravel's only
        // completes the first level. A published copy that ages therefore loses
        // every sub-key added since, without a word. The kit carries this
        // policy for the whole suite; it is not copied here.
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

        // One file per area: the administration with its screens, the public
        // side with its ingestion endpoint. Both are always loaded — a file we
        // stopped loading would be dead code that looks alive.
        $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Where the package's compiled files are. The kit reads this registry
        // to build their address, and to compare the published copy with the
        // source: an older copy raises rather than quietly serving last
        // month's stylesheet.
        $this->app->make(AssetRegistry::class)->register('analytics', __DIR__.'/../public');

        $this->registerPersistentMiddleware();
        $this->exemptTheConsentCookie();

        /*
         * The components, under a single prefix and through two mechanisms.
         *
         * Laravel looks for a class first, and falls back on the anonymous view
         * when there is none: `<x-analytics::page>` reaches the class,
         * `<x-analytics::kpi-card>` the view.
         *
         * Page and root are classes because they open the kit's context before
         * their slot, and a view can do nothing before it is rendered.
         */
        Blade::componentNamespace('Falcon\\Analytics\\View\\Components', 'analytics');
        Blade::anonymousComponentNamespace('analytics::components', 'analytics');

        /*
         * The package's only directive: it brings the whole collector to a
         * public page of the host, its configuration included.
         *
         * It was called `@analyticsConfig` back when it laid down nothing but a
         * configuration object, the code coming from the host's JavaScript
         * entry. The package now compiles and ships its script, so the
         * directive brings it — and the name says so.
         *
         * It renders a view rather than HTML built here: an asset declaration
         * has to go through the kit's component, not behind its back.
         */
        Blade::directive('analyticsCollector', fn (): string => "<?php echo view('analytics::collector')->render(); ?>");

        /*
         * The screens and their blocks, by namespace.
         *
         * Thirty lines of manual registration used to live here, one per class.
         * A screen added without its line did not exist, and the absence only
         * showed on screen.
         *
         * The name is now derived from the class path: the one carrying the
         * overview answers to `<livewire:analytics::admin.overview-page />`,
         * and a block filed under `Widgets` to `admin.widgets.trend-chart`.
         *
         * Livewire only discovers `app/Livewire` on its own; a package's
         * classes live elsewhere, hence this declaration.
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
     * The consent cookie, exempted from encryption by the package itself.
     *
     * A consent banner writes that cookie in JavaScript, so in clear. Read back
     * through `EncryptCookies` it decrypts to `null`, consent is never seen, and
     * **every visitor stays session-scoped without a word** — the exact symptom
     * of having forgotten the exemption.
     *
     * This used to be a line the host had to add to `bootstrap/app.php`, and it
     * was the most silent of the four things asked of it. The kit already does
     * the same for its own three cookies; a package can do it for its own.
     *
     * Nothing happens while no cookie is named: with no consent cookie, the
     * package never promotes a visitor anyway.
     */
    private function exemptTheConsentCookie(): void
    {
        $cookie = config('analytics.identity.consent_cookie');

        if (is_string($cookie) && $cookie !== '' && class_exists(EncryptCookies::class)) {
            EncryptCookies::except([$cookie]);
        }
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
