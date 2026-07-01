<?php

declare(strict_types=1);

namespace Falcon\Analytics;

use Falcon\Analytics\Console\GeoipDownloadCommand;
use Falcon\Analytics\Console\InstallCommand;
use Falcon\Analytics\Support\GeoResolver;
use Illuminate\Support\ServiceProvider;

final class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/analytics.php', 'analytics');

        $this->app->singleton(Analytics::class);

        $this->app->singleton(GeoResolver::class, fn (): GeoResolver => new GeoResolver(
            config('analytics.geoip.database_path') ?: null,
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/analytics.php');

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
