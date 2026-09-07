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
use Falcon\Analytics\Livewire\Dashboard\AdDetailPage;
use Falcon\Analytics\Livewire\Dashboard\AdsPage;
use Falcon\Analytics\Livewire\Dashboard\CampaignDetailPage;
use Falcon\Analytics\Livewire\Dashboard\CampaignsPage;
use Falcon\Analytics\Livewire\Dashboard\EventsPage;
use Falcon\Analytics\Livewire\Dashboard\FunnelsPage;
use Falcon\Analytics\Livewire\Dashboard\IntegrationsPage;
use Falcon\Analytics\Livewire\Dashboard\MarketingDashboardPage;
use Falcon\Analytics\Livewire\Dashboard\OverviewPage;
use Falcon\Analytics\Livewire\Dashboard\RealtimePage;
use Falcon\Analytics\Livewire\Dashboard\SessionDetailPage;
use Falcon\Analytics\Livewire\Dashboard\SessionsPage;
use Falcon\Analytics\Livewire\Dashboard\VisitorDetailPage;
use Falcon\Analytics\Livewire\Dashboard\VisitorsPage;
use Falcon\Analytics\Livewire\Dashboard\Widgets\AdDetailContent;
use Falcon\Analytics\Livewire\Dashboard\Widgets\CampaignDetailContent;
use Falcon\Analytics\Livewire\Dashboard\Widgets\EventsContent;
use Falcon\Analytics\Livewire\Dashboard\Widgets\FunnelsContent;
use Falcon\Analytics\Livewire\Dashboard\Widgets\MarketingDashboardContent;
use Falcon\Analytics\Livewire\Dashboard\Widgets\OverviewAcquisition;
use Falcon\Analytics\Livewire\Dashboard\Widgets\OverviewAudience;
use Falcon\Analytics\Livewire\Dashboard\Widgets\OverviewContent;
use Falcon\Analytics\Livewire\Dashboard\Widgets\OverviewEvents;
use Falcon\Analytics\Livewire\Dashboard\Widgets\OverviewHeadline;
use Falcon\Analytics\Livewire\Dashboard\Widgets\OverviewSearchQueries;
use Falcon\Analytics\Livewire\Dashboard\Widgets\SessionsHeadline;
use Falcon\Analytics\Livewire\Dashboard\Widgets\TrendChart;
use Falcon\Analytics\Livewire\Dashboard\Widgets\VisitorsHeadline;
use Falcon\Analytics\Support\GeoResolver;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/analytics.php', 'analytics');

        $this->app->singleton(Analytics::class);

        $this->app->singleton(GeoResolver::class, fn (): GeoResolver => new GeoResolver(
            config('analytics.geoip.database_path') ?: null,
            config('analytics.geoip.dev_ip') ?: null,
        ));

        $this->app->singleton(FunnelRegistry::class, function (): FunnelRegistry {
            $registry = new FunnelRegistry;
            $path = config('analytics.funnels_path') ?: base_path('app/Analytics/funnels.php');

            if (is_string($path) && is_file($path)) {
                $registry->load($path);
            }

            return $registry;
        });

        $this->app->singleton(EventRegistry::class, function (): EventRegistry {
            $registry = new EventRegistry;
            $path = config('analytics.events_path') ?: base_path('app/Analytics/events.php');

            if (is_string($path) && is_file($path)) {
                $registry->load($path);
            }

            return $registry;
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'analytics');
        $this->loadRoutesFrom(__DIR__.'/../routes/analytics.php');

        $this->registerPersistentMiddleware();
        $this->shareLayoutWithScreens();

        Blade::anonymousComponentNamespace('analytics::components', 'analytics');

        // La seule directive du paquet, et elle ne porte que des donnees du
        // serveur · le nom de la route courante, et le suivi coupe quand
        // l'administratrice est connectee. Le code du collecteur, lui, est
        // importe par l'hote dans son entree publique et compile par son build.
        // Elle s'appelait `analyticsScripts` jusqu'au 2026-09-06, quand elle
        // portait encore la balise du script.
        Blade::directive('analyticsConfig', fn (): string => '<?php echo \Falcon\Analytics\View\Collector::render(); ?>');

        /*
         * Les quatorze ecrans. Ils etaient montes par `Route::livewire`, donc
         * nommes par leur classe et jamais enregistres ; ils sont desormais
         * embarques par une vue mince, qui les appelle par leur alias.
         */
        Livewire::component('analytics-overview', OverviewPage::class);
        Livewire::component('analytics-realtime', RealtimePage::class);
        Livewire::component('analytics-visitors', VisitorsPage::class);
        Livewire::component('analytics-visitor-detail', VisitorDetailPage::class);
        Livewire::component('analytics-events', EventsPage::class);
        Livewire::component('analytics-funnels', FunnelsPage::class);
        Livewire::component('analytics-sessions', SessionsPage::class);
        Livewire::component('analytics-session-detail', SessionDetailPage::class);
        Livewire::component('analytics-integrations', IntegrationsPage::class);
        Livewire::component('analytics-marketing-dashboard', MarketingDashboardPage::class);
        Livewire::component('analytics-campaigns', CampaignsPage::class);
        Livewire::component('analytics-campaign-detail', CampaignDetailPage::class);
        Livewire::component('analytics-ads', AdsPage::class);
        Livewire::component('analytics-ad-detail', AdDetailPage::class);

        // Les blocs differes, qui vivent a l'interieur d'un ecran.
        Livewire::component('analytics-trend-chart', TrendChart::class);
        Livewire::component('analytics-events-content', EventsContent::class);
        Livewire::component('analytics-marketing-dashboard-content', MarketingDashboardContent::class);
        Livewire::component('analytics-funnels-content', FunnelsContent::class);
        Livewire::component('analytics-ad-detail-content', AdDetailContent::class);
        Livewire::component('analytics-campaign-detail-content', CampaignDetailContent::class);
        Livewire::component('analytics-overview-headline', OverviewHeadline::class);
        Livewire::component('analytics-overview-audience', OverviewAudience::class);
        Livewire::component('analytics-overview-acquisition', OverviewAcquisition::class);
        Livewire::component('analytics-overview-content', OverviewContent::class);
        Livewire::component('analytics-overview-events', OverviewEvents::class);
        Livewire::component('analytics-overview-search-queries', OverviewSearchQueries::class);
        Livewire::component('analytics-sessions-headline', SessionsHeadline::class);
        Livewire::component('analytics-visitors-headline', VisitorsHeadline::class);

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
             * Les vues, pour l'hote qui veut envelopper un ecran · y poser un
             * bandeau, un fil d'Ariane, un conteneur a lui. Ce sont les vues
             * minces qui l'interessent · trois lignes chacune, et le corps de
             * l'ecran reste au paquet, donc il continue d'etre mis a jour.
             *
             * Rien n'oblige a publier · une vue posee dans
             * `resources/views/vendor/analytics/` est prise en compte de toute
             * facon. La commande evite seulement de recopier a la main.
             */
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/analytics'),
            ], 'analytics-views');
        }
    }

    /**
     * Tell the thin screen views which layout to extend, and in which section.
     *
     * A screen names neither: it says what it shows, and stays unaware of how it
     * is being mounted. The host decides, from its config, and gets the package
     * standalone shell while it decides nothing.
     *
     * Two composers rather than one because the two modules are configured
     * separately — a host can hang marketing off another shell than analytics,
     * or not mount it at all.
     */
    private function shareLayoutWithScreens(): void
    {
        foreach (['dashboard', 'marketing'] as $module) {
            View::composer("analytics::{$module}.*", static function ($view) use ($module): void {
                $view->with([
                    'analyticsLayout' => config("analytics.{$module}.layout") ?: 'analytics::layouts.dashboard',
                    'analyticsSection' => config("analytics.{$module}.layout_section", 'content'),
                ]);
            });
        }
    }

    /**
     * Replay the host's dashboard and marketing middleware on every Livewire
     * component update (/livewire/update). Livewire only re-runs middleware
     * registered as persistent, so without this a signed component snapshot
     * from a formerly authorized session could keep triggering actions (GDPR
     * erasure, campaign CRUD) after the host middleware would deny the page.
     * The 'web' stack is excluded: Livewire already runs it on updates.
     */
    private function registerPersistentMiddleware(): void
    {
        $middleware = array_values(array_unique(array_filter(
            [
                ...(array) config('analytics.dashboard.middleware', []),
                ...(array) config('analytics.marketing.middleware', []),
            ],
            fn (mixed $entry): bool => is_string($entry) && $entry !== '' && $entry !== 'web',
        )));

        if ($middleware !== []) {
            Livewire::addPersistentMiddleware($middleware);
        }
    }
}
