<?php

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\IngestionContext;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\SessionWriteRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function visitorRow(): Visitor
{
    return Visitor::create([
        'uuid' => (string) Str::uuid(),
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);
}

it('starts a session mapping the resolved context', function () {
    $repo = new SessionWriteRepository;
    $visitor = visitorRow();

    $context = new IngestionContext(
        ip: '85.4.12.66',
        country: 'CH',
        region: 'Geneva',
        city: 'Geneva',
        latitude: 46.2044,
        longitude: 6.1432,
        deviceType: 'desktop',
        browser: 'Firefox',
        os: 'Windows',
        referrer: 'https://google.com',
        source: 'google',
        utmCampaign: 'spring',
        landingRoute: 'home',
        landingUrl: 'https://vantadrive.ch/',
        subjectType: 'client',
        subjectId: 7,
    );

    $session = $repo->start($visitor, $context, CarbonImmutable::parse('2026-06-30 09:00:00'))->fresh();

    expect($session->visitor_id)->toBe($visitor->id)
        ->and($session->started_at->toDateTimeString())->toBe('2026-06-30 09:00:00')
        ->and($session->last_activity_at->toDateTimeString())->toBe('2026-06-30 09:00:00')
        ->and($session->ended_at)->toBeNull()
        ->and($session->ip)->toBe('85.4.12.66')
        ->and($session->country)->toBe('CH')
        ->and($session->city)->toBe('Geneva')
        ->and($session->latitude)->toEqualWithDelta(46.2044, 0.00001)
        ->and($session->browser)->toBe('Firefox')
        ->and($session->source)->toBe('google')
        ->and($session->utm_campaign)->toBe('spring')
        ->and($session->landing_route)->toBe('home')
        ->and($session->subject_type)->toBe('client')
        ->and($session->subject_id)->toBe(7)
        ->and($session->is_bot)->toBeFalse()
        ->and($session->pageview_count)->toBe(0);
});

it('records activity atomically with counters', function () {
    $repo = new SessionWriteRepository;
    $visitor = visitorRow();
    $session = $repo->start($visitor, new IngestionContext, CarbonImmutable::parse('2026-06-30 09:00:00'));

    $repo->recordActivity($session, CarbonImmutable::parse('2026-06-30 09:05:00'), pageviewDelta: 2, eventDelta: 5);
    $repo->recordActivity($session, CarbonImmutable::parse('2026-06-30 09:08:00'), pageviewDelta: 1, eventDelta: 3);
    // An out-of-order older batch must not regress the timestamp, but still counts.
    $repo->recordActivity($session, CarbonImmutable::parse('2026-06-30 09:02:00'), pageviewDelta: 1, eventDelta: 1);

    $fresh = $session->fresh();
    expect($fresh->pageview_count)->toBe(4)
        ->and($fresh->event_count)->toBe(9)
        ->and($fresh->last_activity_at->toDateTimeString())->toBe('2026-06-30 09:08:00');
});
