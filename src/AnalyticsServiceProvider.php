<?php

declare(strict_types=1);

namespace Falcon\Analytics;

use Falcon\Analytics\Console\CheckEventsCommand;
use Falcon\Analytics\Console\GeoipDownloadCommand;
use Falcon\Analytics\Console\InstallCommand;
use Falcon\Analytics\Console\PruneCommand;
use Falcon\Analytics\Console\ScanEventsCommand;
use Falcon\Analytics\Console\SweepCommand;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Livewire\Dashboard\Widgets\EventsTrendChart;
use Falcon\Analytics\Livewire\Dashboard\Widgets\MarketingTrendChart;
use Falcon\Analytics\Livewire\Dashboard\Widgets\OverviewAcquisition;
use Falcon\Analytics\Livewire\Dashboard\Widgets\OverviewAudience;
use Falcon\Analytics\Livewire\Dashboard\Widgets\OverviewContent;
use Falcon\Analytics\Livewire\Dashboard\Widgets\OverviewEvents;
use Falcon\Analytics\Livewire\Dashboard\Widgets\TrendChart;
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

        Blade::anonymousComponentNamespace('analytics::components', 'analytics');
        Blade::directive('analyticsScripts', fn (): string => '<?php echo \Falcon\Analytics\View\Collector::render(); ?>');

        Livewire::component('analytics-trend-chart', TrendChart::class);
        Livewire::component('analytics-events-trend-chart', EventsTrendChart::class);
        Livewire::component('analytics-marketing-trend-chart', MarketingTrendChart::class);
        Livewire::component('analytics-overview-audience', OverviewAudience::class);
        Livewire::component('analytics-overview-acquisition', OverviewAcquisition::class);
        Livewire::component('analytics-overview-content', OverviewContent::class);
        Livewire::component('analytics-overview-events', OverviewEvents::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/analytics.php' => config_path('analytics.php'),
            ], 'analytics-config');

            $this->commands([
                InstallCommand::class,
                GeoipDownloadCommand::class,
                PruneCommand::class,
                SweepCommand::class,
                ScanEventsCommand::class,
                CheckEventsCommand::class,
            ]);

            // Self-schedule maintenance so a host only needs the standard
            // schedule:run cron, never a dedicated analytics cron.
            $this->app->booted(function (): void {
                $schedule = $this->app->make(Schedule::class);

                // Close idle sessions and prune expired raw events on a fixed cadence.
                $schedule->command('analytics:sweep')->everyFiveMinutes()->withoutOverlapping();
                $schedule->command('analytics:prune')->dailyAt('03:30')->withoutOverlapping();

                // Refresh the GeoLite2 database monthly; inert until a licence key is set.
                $schedule->command('analytics:geoip:download')
                    ->monthlyOn(1, '04:00')
                    ->withoutOverlapping()
                    ->when(fn (): bool => (string) config('analytics.geoip.license_key') !== '');
            });
        }
    }
}
