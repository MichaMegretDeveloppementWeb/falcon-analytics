<?php

declare(strict_types=1);

namespace Falcon\Analytics;

use Falcon\Analytics\Console\CheckEventsCommand;
use Falcon\Analytics\Console\GeoipDownloadCommand;
use Falcon\Analytics\Console\InstallCommand;
use Falcon\Analytics\Console\PruneCommand;
use Falcon\Analytics\Console\ScanEventsCommand;
use Falcon\Analytics\Console\SweepCommand;
use Falcon\Analytics\Console\SyncSearchConsoleCommand;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
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

        Blade::anonymousComponentNamespace('analytics::components', 'analytics');
        Blade::directive('analyticsScripts', fn (): string => '<?php echo \Falcon\Analytics\View\Collector::render(); ?>');

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
            GeoipDownloadCommand::class,
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
