<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Console\Command;

final class EventsCommandsTest extends TestCase
{
    public function test_it_passes_the_funnel_check_when_every_step_event_is_declared(): void
    {
        config([
            'analytics.funnels_path' => __DIR__.'/../Fixtures/analytics-funnels.php',
            'analytics.events_path' => __DIR__.'/../Fixtures/analytics-events.php',
        ]);
        $this->app->forgetInstance(FunnelRegistry::class);
        $this->app->forgetInstance(EventRegistry::class);

        $this->artisan('analytics:events:check')->assertExitCode(Command::SUCCESS);
    }

    public function test_it_fails_the_funnel_check_when_a_step_event_is_not_declared(): void
    {
        config([
            'analytics.funnels_path' => __DIR__.'/../Fixtures/analytics-funnels.php',
            'analytics.events_path' => __DIR__.'/../Fixtures/analytics-events-other.php',
        ]);
        $this->app->forgetInstance(FunnelRegistry::class);
        $this->app->forgetInstance(EventRegistry::class);

        $this->artisan('analytics:events:check')
            ->expectsOutputToContain('sample.action')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_it_reports_code_events_not_declared_from_attributes_literals_and_enum_names(): void
    {
        config([
            'analytics.events_path' => __DIR__.'/../Fixtures/analytics-events.php',
            'analytics.events_scan_paths' => [__DIR__.'/../Fixtures/scan'],
        ]);
        $this->app->forgetInstance(EventRegistry::class);

        $this->artisan('analytics:events:scan')
            ->expectsOutputToContain('sample.click')   // attribut data-track-event
            ->expectsOutputToContain('sample.server')  // record('literal')
            ->expectsOutputToContain('Foo')            // record(Enum::Case->name)
            ->assertExitCode(Command::FAILURE);
    }
}
