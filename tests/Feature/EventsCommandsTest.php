<?php

use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Illuminate\Console\Command;

it('passes the funnel check when every step event is declared', function () {
    config([
        'analytics.funnels_path' => __DIR__.'/../Fixtures/analytics-funnels.php',
        'analytics.events_path' => __DIR__.'/../Fixtures/analytics-events.php',
    ]);
    $this->app->forgetInstance(FunnelRegistry::class);
    $this->app->forgetInstance(EventRegistry::class);

    $this->artisan('analytics:events:check')->assertExitCode(Command::SUCCESS);
});

it('fails the funnel check when a step event is not declared', function () {
    config([
        'analytics.funnels_path' => __DIR__.'/../Fixtures/analytics-funnels.php',
        'analytics.events_path' => __DIR__.'/../Fixtures/analytics-events-other.php',
    ]);
    $this->app->forgetInstance(FunnelRegistry::class);
    $this->app->forgetInstance(EventRegistry::class);

    $this->artisan('analytics:events:check')
        ->expectsOutputToContain('sample.action')
        ->assertExitCode(Command::FAILURE);
});

it('reports code events not declared, from attributes, literals and enum names', function () {
    config([
        'analytics.events_path' => __DIR__.'/../Fixtures/analytics-events.php',
        'analytics.events_scan_paths' => [__DIR__.'/../Fixtures/scan'],
    ]);
    $this->app->forgetInstance(EventRegistry::class);

    $this->artisan('analytics:events:scan')
        ->expectsOutputToContain('sample.click')   // data-track-event attribute
        ->expectsOutputToContain('sample.server')  // record('literal')
        ->expectsOutputToContain('Foo')            // record(Enum::Case->name)
        ->assertExitCode(Command::FAILURE);
});
