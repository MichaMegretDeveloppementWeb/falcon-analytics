<?php

declare(strict_types=1);

namespace Falcon\Analytics;

use Falcon\Analytics\Console\GeoipDownloadCommand;
use Falcon\Analytics\Console\InstallCommand;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Livewire\Dashboard\Widgets\TrendChart;
use Falcon\Analytics\Support\GeoResolver;
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
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'analytics');
        $this->loadRoutesFrom(__DIR__.'/../routes/analytics.php');

        Blade::anonymousComponentNamespace('analytics::components', 'analytics');
        Blade::directive('analyticsScripts', fn (): string => '<?php echo \Falcon\Analytics\View\Collector::render(); ?>');

        Livewire::component('analytics-trend-chart', TrendChart::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/analytics.php' => config_path('analytics.php'),
            ], 'analytics-config');

            $this->commands([
                InstallCommand::class,
                GeoipDownloadCommand::class,
            ]);
        }
    }
}
