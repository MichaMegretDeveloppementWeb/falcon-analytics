<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class ModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_casts_session_attributes(): void
    {
        $session = Session::factory()->create();
        $session->update(['latitude' => 46.2044, 'longitude' => 6.1432, 'is_bot' => false]);

        $fresh = $session->fresh();

        $this->assertNotNull($fresh);

        // Generic accessor: the property's annotation already promises the cast, so it could not fail.
        $this->assertInstanceOf(CarbonImmutable::class, $fresh->getAttribute('started_at'));
        $this->assertNull($fresh->ended_at);
        $this->assertFalse($fresh->is_bot);
        $this->assertSame(46.2044, $fresh->latitude);

        // Generic accessor: only the cast guarantees integers, whatever the driver hands over.
        $this->assertSame(0, $fresh->getAttribute('pageview_count'));
        $this->assertSame(0, $fresh->getAttribute('click_count'));
        $this->assertSame(0, $fresh->getAttribute('event_count'));
    }

    public function test_it_casts_the_event_type_to_the_enum_props_to_an_array_and_value_to_float(): void
    {
        $event = Event::factory()->click('listing.contact_click')->create([
            'props' => ['listing_id' => 42],
            'value' => 3,
        ]);

        $fresh = $event->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame(EventType::Click, $fresh->type);
        $this->assertSame(['listing_id' => 42], $fresh->props);
        $this->assertInstanceOf(CarbonImmutable::class, $fresh->getAttribute('occurred_at'));
        $this->assertSame(3, $fresh->value);
    }

    public function test_it_wires_the_relationships_in_both_directions(): void
    {
        $visitor = Visitor::factory()->create();
        $session = Session::factory()->for($visitor)->create();

        $event = Event::factory()->for($session)->create();

        $firstSession = $visitor->sessions->first();
        $firstEvent = $visitor->events->first();

        $this->assertNotNull($firstSession);
        $this->assertNotNull($firstEvent);

        $this->assertTrue($session->visitor->is($visitor));
        $this->assertTrue($firstSession->is($session));
        $this->assertTrue($event->session->is($session));
        $this->assertTrue($event->visitor->is($visitor));
        $this->assertTrue($firstEvent->is($event));
    }

    public function test_it_disables_timestamps_on_every_model(): void
    {
        $this->assertFalse((new Visitor)->usesTimestamps());
        $this->assertFalse((new Session)->usesTimestamps());
        $this->assertFalse((new Event)->usesTimestamps());
    }
}
