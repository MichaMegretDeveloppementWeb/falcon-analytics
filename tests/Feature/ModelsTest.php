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
use Illuminate\Support\Str;

final class ModelsTest extends TestCase
{
    use RefreshDatabase;

    private function makeVisitor(): Visitor
    {
        return Visitor::create([
            'uuid' => (string) Str::uuid(),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    private function makeSession(Visitor $visitor): Session
    {
        return Session::create([
            'visitor_id' => $visitor->id,
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);
    }

    public function test_it_casts_session_attributes(): void
    {
        $session = $this->makeSession($this->makeVisitor());
        $session->update(['latitude' => 46.2044, 'longitude' => 6.1432, 'is_bot' => false]);

        $fresh = $session->fresh();

        $this->assertNotNull($fresh);

        // Through the generic accessor, not the property: what is checked here
        // is the cast, and the model's annotation already promises it. Going
        // through the property would make an assertion that can no longer fail.
        $this->assertInstanceOf(CarbonImmutable::class, $fresh->getAttribute('started_at'));
        $this->assertNull($fresh->ended_at);
        $this->assertFalse($fresh->is_bot);
        $this->assertSame(46.2044, $fresh->latitude);

        // The three counters, through the generic accessor: they are the ones
        // an addition reads, and only the cast guarantees they come back as
        // integers whatever the driver hands over.
        $this->assertSame(0, $fresh->getAttribute('pageview_count'));
        $this->assertSame(0, $fresh->getAttribute('click_count'));
        $this->assertSame(0, $fresh->getAttribute('event_count'));
    }

    public function test_it_casts_the_event_type_to_the_enum_props_to_an_array_and_value_to_float(): void
    {
        $visitor = $this->makeVisitor();

        $event = Event::create([
            'session_id' => $this->makeSession($visitor)->id,
            'visitor_id' => $visitor->id,
            'occurred_at' => now(),
            'type' => EventType::Click,
            'name' => 'listing.contact_click',
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
        $visitor = $this->makeVisitor();
        $session = $this->makeSession($visitor);

        $event = Event::create([
            'session_id' => $session->id,
            'visitor_id' => $visitor->id,
            'occurred_at' => now(),
            'type' => EventType::Pageview,
        ]);

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
