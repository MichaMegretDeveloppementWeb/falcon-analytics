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
        $this->assertSame(5.0, $event->value);
        $this->assertNull($registry->get('sample.other')->value);
        $this->assertNull($registry->get('unknown'));
    }
}
