<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\EventWriteRepository;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

final class EventWriteRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_bulk_inserts_event_rows_and_reads_them_back_through_the_casts(): void
    {
        $visitor = Visitor::create(['uuid' => (string) Str::uuid(), 'first_seen_at' => now(), 'last_seen_at' => now()]);
        $session = Session::create(['visitor_id' => $visitor->id, 'started_at' => now(), 'last_activity_at' => now()]);
        $at = CarbonImmutable::parse('2026-06-30 09:00:00')->toDateTimeString();

        (new EventWriteRepository)->insertBatch([
            ['session_id' => $session->id, 'visitor_id' => $visitor->id, 'occurred_at' => $at, 'type' => 'pageview', 'name' => null, 'props' => null, 'value' => null],
            ['session_id' => $session->id, 'visitor_id' => $visitor->id, 'occurred_at' => $at, 'type' => 'click', 'name' => 'listing.contact_click', 'props' => json_encode(['listing_id' => 42]), 'value' => 3],
        ]);

        $this->assertSame(2, Event::count());

        $click = Event::query()->where('type', EventType::Click->value)->firstOrFail();

        $this->assertSame(EventType::Click, $click->type);
        $this->assertSame('listing.contact_click', $click->name);
        $this->assertSame(['listing_id' => 42], $click->props);
        $this->assertSame(3.0, $click->value);
        $this->assertSame('2026-06-30 09:00:00', $click->occurred_at->toDateTimeString());
    }

    public function test_it_does_nothing_for_an_empty_batch(): void
    {
        (new EventWriteRepository)->insertBatch([]);

        $this->assertSame(0, Event::count());
    }
}
