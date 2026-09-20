<?php

declare(strict_types=1);

namespace Falcon\Analytics;

use Falcon\Analytics\Console\ArchiveCommand;
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
use Falcon\Analytics\Http\Middleware\CatchesUpTheMaintenance;
use Falcon\Analytics\Support\GeoResolver;
use Falcon\Ui\AssetRegistry;
use Falcon\Ui\Config\CompletesDefaults;
use Falcon\Ui\View\Leaves;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
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

        /*
         * The package's own settings, and they are not the host's.
         *
         * Posed AFTER the host's configuration and without regard for what a
         * published copy might say: these are design decisions, not questions.
         * A key of that file copied into `config/analytics.php` therefore has
         * no effect, which is the point — a value that suits nobody is a defect
         * to fix here, not a question to ask of every project.
         *
         * They live in a file rather than in constants so they read in one
         * place, change in one line, and can be moved for the length of an
         * essay.
         */
        $this->app->make(ConfigRepository::class)->set('analytics.internal', require __DIR__.'/../config/internal.php');

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

        $this->mountTheScreens();

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

        // A list repeats these once per row, a campaign once per condition:
        // the kit runs their file directly.
        $this->app->make(Leaves::class)->add(
            'analytics::components.source',
            'analytics::components.page-url',
            'analytics::components.country',
            'analytics::components.condition-chip',
        );

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
            ArchiveCommand::class,
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
            // Close idle sessions on a fixed cadence.
            $schedule->command('analytics:sweep')->everyFiveMinutes()->withoutOverlapping();

            /*
             * Summarise, then erase, and in that order. The purge refuses a day
             * the archiving has not treated, so a scheduler that stops running
             * loses nothing: both halt together and the backlog is caught up
             * later. Half an hour apart so a long catch-up does not meet its
             * own purge.
             */
            $schedule->command('analytics:archive')->dailyAt('03:00')->withoutOverlapping();
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
     * Where every screen of the package mounts, and behind what.
     *
     * **One file tells all of it**, and that is why the groups are here rather
     * than in the route files themselves: an address or a guard of this package
     * is read in one place, never hunted for.
     *
     * **One area, and its mount points.** An area is its shell and its name
     * prefix; marketing shares both with the analytics screens — same
     * administration, one chrome — while carrying its own address and its own
     * guards. Three of its screens WRITE, where the eleven others only read, so
     * a host is entitled to put them behind another guard.
     *
     * **Siblings, never nested**, and it is measured rather than assumed · a
     * nested group concatenates the prefixes and ACCUMULATES the middleware, so
     * marketing would answer at `/admin/analytics/admin/marketing/…` and demand
     * both guards at once. Nobody would get in.
     *
     * The middleware must carry a session stack (`web`, typically): package
     * routes are registered outside the host's own groups, so they inherit
     * nothing.
     */
    private function mountTheScreens(): void
    {
        /** @var array<string, mixed> $admin */
        $admin = (array) config('analytics.admin', []);

        /** @var array<string, mixed> $marketing */
        $marketing = (array) ($admin['marketing'] ?? []);

        $this->mount(
            $admin,
            'admin/analytics',
            'analytics.admin.',
            __DIR__.'/../routes/admin.php',
            'Analytics screens mounted with an empty middleware list: they are publicly reachable.',
        );

        $this->mount(
            $marketing,
            'admin/marketing',
            'analytics.admin.marketing.',
            __DIR__.'/../routes/marketing.php',
            'Analytics marketing screens mounted with an empty middleware list: they are publicly reachable.',
        );

        // The public side: the collector posts here, and nothing else lives at
        // this level. Its own stack is written in the file, the origin check
        // and the rate limit going with it.
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }

    /**
     * One mount point · its address, its guards, its name prefix, its file.
     *
     * **The package appends one middleware of its own after the host's list**,
     * which catches the maintenance up when a scheduler has stopped. Appended
     * rather than configured, for the same reason the origin check is appended
     * to the collection endpoint: what the package depends on to do its job is
     * not something a host removes by emptying a setting. Switching it off is a
     * setting of its own, `maintenance.on_screen_load`.
     *
     * @param  array<string, mixed>  $area
     */
    private function mount(array $area, string $prefix, string $name, string $file, string $warning): void
    {
        // An explicitly empty list mounts the screens with no protection at all
        // — no session, no auth. Almost certainly a host misconfiguration, so
        // say it rather than serve them quietly.
        if (($area['middleware'] ?? null) === []) {
            Log::channel(config('analytics.log_channel'))->warning($warning);
        }

        Route::prefix((string) ($area['route_prefix'] ?? $prefix))
            ->middleware([...((array) ($area['middleware'] ?? ['web', 'auth'])), CatchesUpTheMaintenance::class])
            ->name($name)
            ->group(fn () => $this->loadRoutesFrom($file));
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
