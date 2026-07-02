<?php

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Http\Requests\IngestBatchRequest;
use Illuminate\Support\Facades\Validator;

function ingestRequest(array $data): IngestBatchRequest
{
    $request = IngestBatchRequest::create('/__analytics', 'POST', $data);
    $request->headers->set('Accept', 'application/json');
    $request->setContainer(app());
    $request->validateResolved();

    return $request;
}

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('validates and maps a batch, rebuilding timestamps from client deltas', function () {
    CarbonImmutable::setTestNow('2026-07-01 12:00:00');

    $batch = ingestRequest([
        'sent_at' => 10_000,
        'referrer' => 'https://google.com',
        'events' => [
            ['type' => 'pageview', 'ts' => 10_000, 'route' => 'home', 'url' => 'https://vantadrive.ch/'],
            ['type' => 'click', 'ts' => 7_000, 'name' => 'listing.contact_click', 'props' => ['listing_id' => 42], 'value' => 3],
        ],
    ])->toBatch();

    expect($batch->events)->toHaveCount(2)
        ->and($batch->referrer)->toBe('https://google.com')
        ->and($batch->events[0]->type)->toBe(EventType::Pageview)
        ->and($batch->events[0]->occurredAt->toDateTimeString())->toBe('2026-07-01 12:00:00')
        ->and($batch->events[1]->type)->toBe(EventType::Click)
        ->and($batch->events[1]->occurredAt->toDateTimeString())->toBe('2026-07-01 11:59:57')
        ->and($batch->events[1]->value)->toBe(3.0)
        ->and($batch->events[1]->props)->toBe(['listing_id' => 42]);
});

it('caps the reconstructed age of very old events', function () {
    CarbonImmutable::setTestNow('2026-07-01 12:00:00');

    $batch = ingestRequest([
        'sent_at' => 10_000_000,
        'events' => [['type' => 'pageview', 'ts' => 0]], // delta 10_000_000ms, capped at 3_600_000ms (1h)
    ])->toBatch();

    expect($batch->events[0]->occurredAt->toDateTimeString())->toBe('2026-07-01 11:00:00');
});

function ingestFails(array $data): bool
{
    return Validator::make($data, (new IngestBatchRequest)->rules())->fails();
}

it('rejects an invalid event type', function () {
    expect(ingestFails(['sent_at' => 1, 'events' => [['type' => 'bogus', 'ts' => 1]]]))->toBeTrue();
});

it('rejects an empty batch', function () {
    expect(ingestFails(['sent_at' => 1, 'events' => []]))->toBeTrue();
});

it('rejects a missing sent_at', function () {
    expect(ingestFails(['events' => [['type' => 'pageview', 'ts' => 1]]]))->toBeTrue();
});

it('rejects a batch larger than the cap', function () {
    expect(ingestFails(['sent_at' => 1, 'events' => array_fill(0, 101, ['type' => 'pageview', 'ts' => 1])]))->toBeTrue();
});

it('accepts a well-formed batch', function () {
    expect(ingestFails(['sent_at' => 1, 'events' => [['type' => 'pageview', 'ts' => 1]]]))->toBeFalse();
});

it('redacts sensitive query parameters while keeping tracking params', function () {
    config(['analytics.privacy.redact_query_params' => ['token']]);

    $batch = ingestRequest([
        'sent_at' => 1,
        'referrer' => 'https://ref.example/?token=zzz&utm_source=meta',
        'events' => [['type' => 'pageview', 'ts' => 1, 'url' => 'https://vantadrive.ch/?token=secret&gclid=abc']],
    ])->toBatch();

    expect($batch->events[0]->url)->toContain('token=redacted')
        ->and($batch->events[0]->url)->toContain('gclid=abc')
        ->and($batch->events[0]->url)->not->toContain('secret')
        ->and($batch->referrer)->toContain('token=redacted')
        ->and($batch->referrer)->toContain('utm_source=meta');
});
