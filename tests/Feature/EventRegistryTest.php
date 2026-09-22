<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Tests\TestCase;

final class EventRegistryTest extends TestCase
{
    public function test_it_loads_tracked_events_declared_in_the_configured_file(): void
    {
        config(['analytics.events_path' => __DIR__.'/../Fixtures/analytics-events.php']);
        $this->app->forgetInstance(EventRegistry::class);

        $registry = app(EventRegistry::class);

        $this->assertCount(2, $registry->all());
        $this->assertSame(['sample.action', 'sample.other'], $registry->names());

        $event = $registry->get('sample.action');

        $this->assertNotNull($event);
        $this->assertSame('Sample action', $event->label);
        $this->assertSame(5, $event->value);
        $other = $registry->get('sample.other');

        $this->assertNotNull($other);
        $this->assertNull($other->value);
        $this->assertNull($registry->get('unknown'));
        $this->assertNull($registry->failure());
    }

    public function test_it_keeps_what_came_before_a_failure_and_the_failure_itself(): void
    {
        config(['analytics.events_path' => __DIR__.'/../Fixtures/analytics-events-broken.php']);
        $this->app->forgetInstance(EventRegistry::class);

        $registry = app(EventRegistry::class);

        $this->assertSame(['sample.before'], $registry->names());
        $this->assertStringContainsString('Malformed events file.', (string) $registry->failure());
    }
}
