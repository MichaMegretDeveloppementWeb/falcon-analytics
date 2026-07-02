<?php

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\EventWriteRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('bulk inserts event rows and reads them back through the casts', function () {
    $visitor = Visitor::create(['uuid' => (string) Str::uuid(), 'first_seen_at' => now(), 'last_seen_at' => now()]);
    $session = Session::create(['visitor_id' => $visitor->id, 'started_at' => now(), 'last_activity_at' => now()]);
    $at = CarbonImmutable::parse('2026-06-30 09:00:00')->toDateTimeString();

    (new EventWriteRepository)->insertBatch([
        ['session_id' => $session->id, 'visitor_id' => $visitor->id, 'occurred_at' => $at, 'type' => 'pageview', 'name' => null, 'props' => null, 'value' => null],
        ['session_id' => $session->id, 'visitor_id' => $visitor->id, 'occurred_at' => $at, 'type' => 'click', 'name' => 'listing.contact_click', 'props' => json_encode(['listing_id' => 42]), 'value' => 3],
    ]);

    expect(Event::count())->toBe(2);

    $click = Event::query()->where('type', EventType::Click->value)->firstOrFail();
    expect($click->type)->toBe(EventType::Click)
        ->and($click->name)->toBe('listing.contact_click')
        ->and($click->props)->toBe(['listing_id' => 42])
        ->and($click->value)->toBe(3.0)
        ->and($click->occurred_at->toDateTimeString())->toBe('2026-06-30 09:00:00');
});

it('does nothing for an empty batch', function () {
    (new EventWriteRepository)->insertBatch([]);

    expect(Event::count())->toBe(0);
});
