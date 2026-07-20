<?php

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Livewire\Dashboard\RealtimePage;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\Dashboard\RealtimeReadRepository;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\Fixtures\Models\TestClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function rtVisitor(?array $subject = null): Visitor
{
    return Visitor::create([
        'uuid' => (string) Str::uuid(),
        'first_seen_at' => now()->subHour(),
        'last_seen_at' => now(),
        'session_count' => 0,
        'subject_type' => $subject['type'] ?? null,
        'subject_id' => $subject['id'] ?? null,
    ]);
}

function rtSession(array $attrs = [], ?Visitor $visitor = null): Session
{
    $visitor ??= rtVisitor();
    $visitor->increment('session_count');

    return Session::create(array_merge([
        'visitor_id' => $visitor->id,
        'browser_key' => $visitor->uuid,
        'started_at' => now()->subMinutes(2),
        'last_activity_at' => now(),
        'is_bot' => false,
        'pageview_count' => 1,
    ], $attrs));
}

function rtEvent(Session $session, EventType $type, array $attrs = []): Event
{
    return Event::create(array_merge([
        'session_id' => $session->id,
        'visitor_id' => $session->visitor_id,
        'occurred_at' => now()->subMinute(),
        'type' => $type,
    ], $attrs));
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-07-10 12:00:00'));
    $this->repo = new RealtimeReadRepository;
    $this->onlineSince = CarbonImmutable::now()->subSeconds(60);
    $this->windowSince = CarbonImmutable::now()->subMinutes(30);
});

// ── Repository ──────────────────────────────────────

it('counts distinct online visitors within the online window, ignoring stale and bots', function () {
    $twoDevices = rtVisitor();
    rtSession([], $twoDevices);
    rtSession(['last_activity_at' => now()->subSeconds(30)], $twoDevices); // same visitor: counts once
    rtSession(['last_activity_at' => now()->subSeconds(30)]);
    rtSession(['last_activity_at' => now()->subMinutes(5)]); // stale
    rtSession(['is_bot' => true]);                           // bot

    expect($this->repo->onlineCount($this->onlineSince, null))->toBe(2);
});

it('counts the window sessions, visitors and pageviews, activity-based', function () {
    $visitor = rtVisitor();
    $active = rtSession(['started_at' => now()->subHours(2)], $visitor); // started before, still active
    rtSession([], $visitor);
    rtSession(['last_activity_at' => now()->subHours(1)]);              // out of window

    rtEvent($active, EventType::Pageview);
    rtEvent($active, EventType::Pageview, ['occurred_at' => now()->subHours(1)]); // out of window
    rtEvent($active, EventType::Click);

    $counts = $this->repo->windowCounts($this->windowSince, null);

    expect($counts['sessions'])->toBe(2)
        ->and($counts['visitors'])->toBe(1)
        ->and($counts['pageviews'])->toBe(1);
});

it('counts the declared conversions fired within the window', function () {
    $session = rtSession();
    rtEvent($session, EventType::Custom, ['name' => 'Lead']);
    rtEvent($session, EventType::Custom, ['name' => 'Lead', 'occurred_at' => now()->subHours(2)]);
    rtEvent($session, EventType::Custom, ['name' => 'newsletter']);

    expect($this->repo->conversionsCount($this->windowSince, null, ['Lead']))->toBe(1)
        ->and($this->repo->conversionsCount($this->windowSince, null, []))->toBe(0);
});

it('buckets the window pageviews per minute', function () {
    $session = rtSession();
    rtEvent($session, EventType::Pageview, ['occurred_at' => CarbonImmutable::parse('2026-07-10 11:58:10')]);
    rtEvent($session, EventType::Pageview, ['occurred_at' => CarbonImmutable::parse('2026-07-10 11:58:50')]);
    rtEvent($session, EventType::Pageview, ['occurred_at' => CarbonImmutable::parse('2026-07-10 11:59:20')]);

    expect($this->repo->pageviewsPerMinute($this->windowSince, null))->toBe([
        '2026-07-10 11:58' => 2,
        '2026-07-10 11:59' => 1,
    ]);
});

it('bounds and orders the activity feed, newest first', function () {
    $session = rtSession();
    rtEvent($session, EventType::Pageview, ['occurred_at' => now()->subMinutes(3), 'url' => 'https://x.test/old']);
    rtEvent($session, EventType::Click, ['occurred_at' => now()->subMinutes(2), 'target_text' => 'Contact']);
    rtEvent($session, EventType::Pageview, ['occurred_at' => now()->subMinute(), 'url' => 'https://x.test/new']);

    $feed = $this->repo->activityFeed($this->windowSince, null, 2);

    expect($feed)->toHaveCount(2)
        ->and($feed->first()->url)->toBe('https://x.test/new')
        ->and($feed->last()->target_text)->toBe('Contact')
        ->and($feed->first()->relationLoaded('session'))->toBeTrue();
});

it('bounds and orders the recent sessions, most recently active first', function () {
    rtSession(['last_activity_at' => now()->subMinutes(3), 'city' => 'Old']);
    rtSession(['last_activity_at' => now()->subMinute(), 'city' => 'Mid']);
    rtSession(['last_activity_at' => now(), 'city' => 'Fresh']);
    rtSession(['last_activity_at' => now()->subHours(2), 'city' => 'Out']);

    $sessions = $this->repo->recentSessions($this->windowSince, null, 2);

    expect($sessions)->toHaveCount(2)
        ->and($sessions->first()->city)->toBe('Fresh')
        ->and($sessions->last()->city)->toBe('Mid')
        ->and($sessions->first()->relationLoaded('visitor'))->toBeTrue();
});

it('ranks the window top pages, sources and devices, bounded', function () {
    $a = rtSession(['source' => 'organic', 'device_type' => 'desktop']);
    rtSession(['source' => 'organic', 'device_type' => 'mobile']);
    rtSession(['source' => 'direct', 'device_type' => 'desktop']);

    rtEvent($a, EventType::Pageview, ['url' => 'https://x.test/a']);
    rtEvent($a, EventType::Pageview, ['url' => 'https://x.test/a']);
    rtEvent($a, EventType::Pageview, ['url' => 'https://x.test/b']);

    expect($this->repo->topPages($this->windowSince, null, 1))->toBe([['url' => 'https://x.test/a', 'total' => 2]])
        ->and($this->repo->topSources($this->windowSince, null))->toBe([
            ['label' => 'organic', 'total' => 2],
            ['label' => 'direct', 'total' => 1],
        ])
        ->and($this->repo->topDevices($this->windowSince, null)[0])->toBe(['label' => 'desktop', 'total' => 2]);
});

it('narrows the realtime reads to a subject type', function () {
    $client = rtVisitor(['type' => 'client', 'id' => 3]);
    $clientSession = rtSession(['subject_type' => 'client', 'subject_id' => 3], $client);
    rtEvent($clientSession, EventType::Pageview);

    $anonymous = rtSession();
    rtEvent($anonymous, EventType::Pageview);

    expect($this->repo->onlineCount($this->onlineSince, 'client'))->toBe(1)
        ->and($this->repo->windowCounts($this->windowSince, 'client')['pageviews'])->toBe(1)
        ->and($this->repo->topSources($this->windowSince, 'client')[0]['total'])->toBe(1);
});

it('aggregates the map points by locality with online counts, bounded', function () {
    $geneva = ['city' => 'Geneva', 'country' => 'CH', 'latitude' => 46.2044, 'longitude' => 6.1432];
    rtSession($geneva);
    rtSession(array_merge($geneva, ['last_activity_at' => now()->subMinutes(10)])); // recent, not online
    rtSession(['city' => 'Paris', 'country' => 'FR', 'latitude' => 48.8566, 'longitude' => 2.3522]);
    rtSession(array_merge($geneva, ['is_bot' => true]));                            // bot: excluded
    rtSession(array_merge($geneva, ['last_activity_at' => now()->subHours(2)]));    // out of window

    $points = $this->repo->mapPoints($this->windowSince, $this->onlineSince);

    expect($points)->toHaveCount(2)
        ->and($points[0]['city'])->toBe('Geneva')
        ->and($points[0]['total'])->toBe(2)
        ->and($points[0]['online'])->toBe(1)
        ->and($points[1]['city'])->toBe('Paris')
        ->and($points[1]['online'])->toBe(1)
        ->and($this->repo->mapPoints($this->windowSince, $this->onlineSince, 1))->toHaveCount(1);
});

it('counts the unlocated window sessions', function () {
    rtSession();
    rtSession(['city' => 'Geneva', 'country' => 'CH', 'latitude' => 46.2044, 'longitude' => 6.1432]);

    expect($this->repo->unlocatedCount($this->windowSince))->toBe(1);
});

// ── Page ────────────────────────────────────────────

it('renders the realtime page for an admin with the configured poll', function () {
    $marie = TestClient::create(['first_name' => 'Marie', 'last_name' => 'Dupont']);
    $visitor = rtVisitor(['type' => 'client', 'id' => $marie->id]);
    $session = rtSession([], $visitor);
    rtEvent($session, EventType::Pageview, ['url' => 'https://x.test/catalogue']);

    $this->actingAs(TestAdmin::create([]), 'admin')
        ->get(route('analytics.realtime'))
        ->assertSuccessful()
        ->assertSeeText(__('Temps réel'))
        ->assertSeeText(__('Visiteurs en ligne'))
        ->assertSeeText(__('Visiteurs récents'))
        ->assertSeeText('Marie Dupont')
        ->assertSee('wire:poll.10s.visible', false);
});

it('honours an overridden realtime configuration', function () {
    config()->set('analytics.realtime.poll_seconds', 5);

    $this->actingAs(TestAdmin::create([]), 'admin')
        ->get(route('analytics.realtime'))
        ->assertSuccessful()
        ->assertSee('wire:poll.5s.visible', false);
});

it('redirects a guest to the login page', function () {
    $this->get(route('analytics.realtime'))->assertRedirect();
});

it('dispatches the fresh series for the live charts on every tick', function () {
    $session = rtSession(['device_type' => 'desktop', 'source' => 'organic', 'city' => 'Geneva', 'country' => 'CH', 'latitude' => 46.2044, 'longitude' => 6.1432]);
    rtEvent($session, EventType::Pageview, ['url' => 'https://x.test/a']);

    $this->actingAs(TestAdmin::create([]), 'admin');

    Livewire\Livewire::test(RealtimePage::class)
        ->assertDispatched(
            'analytics-realtime-tick',
            fn (string $name, array $params): bool => isset($params['pulse'], $params['devices'], $params['sources'], $params['map'])
                && $params['map']['points'][0]['city'] === 'Geneva'
                && $params['map']['points'][0]['online'] === 1,
        );
});

it('renders the map, the country list and the unlocated note', function () {
    rtSession(['city' => 'Geneva', 'country' => 'CH', 'latitude' => 46.2044, 'longitude' => 6.1432]);
    rtSession();

    $this->actingAs(TestAdmin::create([]), 'admin')
        ->get(route('analytics.realtime'))
        ->assertSuccessful()
        ->assertSeeText(__('Pays'))
        ->assertSeeText(Locale::getDisplayRegion('-CH', app()->getLocale()))
        ->assertSeeText(__('dont 1 session non localisée'));
});

// ── Budget ──────────────────────────────────────────

it('renders the realtime tick within its query budget', function () {
    $marie = TestClient::create(['first_name' => 'Marie', 'last_name' => 'Dupont']);
    $visitor = rtVisitor(['type' => 'client', 'id' => $marie->id]);
    $session = rtSession([], $visitor);
    rtEvent($session, EventType::Pageview, ['url' => 'https://x.test/a']);
    rtEvent($session, EventType::Click, ['target_text' => 'Contact']);
    rtSession();

    $this->actingAs(TestAdmin::create([]), 'admin');

    DB::enableQueryLog();
    DB::flushQueryLog();

    Livewire\Livewire::test(RealtimePage::class);

    $signatures = collect(DB::getQueryLog())
        ->filter(fn (array $q): bool => str_contains($q['query'], 'falcon_analytics_'))
        ->map(fn (array $q): string => $q['query'].'|'.json_encode($q['bindings']));

    // Frozen plan: online + window (sessions, pageviews) + minute buckets +
    // recent sessions (+ visitors) + feed (events + sessions + visitors) +
    // attributor ambiguity check + top pages/sources/devices + map points +
    // unlocated = 16. No duplicate reads within a tick.
    expect($signatures->count() - $signatures->unique()->count())->toBe(0)
        ->and($signatures->count())->toBeLessThanOrEqual(16);
});
