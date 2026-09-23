<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Actions\IngestEventsAction;
use Falcon\Analytics\DTOs\IncomingBatch;
use Falcon\Analytics\DTOs\IncomingEvent;
use Falcon\Analytics\DTOs\RequestSnapshot;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Repositories\EventWriteRepository;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class EventWriteRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_bulk_inserts_event_rows_and_reads_them_back_through_the_casts(): void
    {
        $session = Session::factory()->create();
        $at = CarbonImmutable::parse('2026-06-30 09:00:00')->toDateTimeString();

        (new EventWriteRepository)->insertBatch([
            ['session_id' => $session->id, 'visitor_id' => $session->visitor_id, 'occurred_at' => $at, 'type' => 'pageview', 'name' => null, 'props' => null, 'value' => null],
            ['session_id' => $session->id, 'visitor_id' => $session->visitor_id, 'occurred_at' => $at, 'type' => 'click', 'name' => 'listing.contact_click', 'props' => json_encode(['listing_id' => 42]), 'value' => 3],
        ]);

        $this->assertSame(2, Event::count());

        $click = Event::query()->where('type', EventType::Click->value)->firstOrFail();

        $this->assertSame(EventType::Click, $click->type);
        $this->assertSame('listing.contact_click', $click->name);
        $this->assertSame(['listing_id' => 42], $click->props);
        $this->assertSame(3, $click->value);
        $this->assertSame('2026-06-30 09:00:00', $click->occurred_at->toDateTimeString());
    }

    public function test_it_does_nothing_for_an_empty_batch(): void
    {
        (new EventWriteRepository)->insertBatch([]);

        $this->assertSame(0, Event::count());
    }

    /**
     * The ingestion writes in bulk, past the model's casts and hooks · the row it
     * lays down has to be the row the model would have written, every column of
     * it, or a column added to the model is silently missing from the ingestion.
     */
    public function test_a_row_the_ingestion_writes_is_the_row_the_model_writes(): void
    {
        $at = CarbonImmutable::parse('2026-06-30 09:00:00');
        $this->travelTo($at->addSecond());

        app(IngestEventsAction::class)->execute(
            'u-1',
            ['type' => 'client', 'id' => 7],
            new RequestSnapshot(ip: '203.0.113.66', userAgent: null, host: 'boutique.test'),
            new IncomingBatch(events: [new IncomingEvent(
                type: EventType::Click,
                occurredAt: $at,
                name: 'listing.contact_click',
                route: 'catalog',
                url: 'https://boutique.test/catalogue?utm_source=x#prix',
                targetSelector: 'a.cta',
                targetText: 'Réserver',
                props: ['listing_id' => 42],
                value: 3,
            )]),
        );

        $bulk = Event::query()->sole();

        $ordinary = Event::factory()->for($bulk->session)->click('listing.contact_click', 'Réserver')->create([
            'occurred_at' => $at,
            'route' => 'catalog',
            'url' => 'https://boutique.test/catalogue?utm_source=x#prix',
            'target_selector' => 'a.cta',
            'props' => ['listing_id' => 42],
            'value' => 3,
            'subject_type' => 'client',
            'subject_id' => 7,
        ]);

        $this->assertSame(
            Arr::except((array) DB::table(Event::TABLE)->find($ordinary->id), 'id'),
            Arr::except((array) DB::table(Event::TABLE)->find($bulk->id), 'id'),
        );
    }
}
