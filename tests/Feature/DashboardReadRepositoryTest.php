<?php

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\DashboardReadRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makeDashboardVisitor(): Visitor
{
    return Visitor::create([
        'uuid' => (string) Str::uuid(),
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);
}

function makeDashboardSession(array $attrs = [], ?Visitor $visitor = null): Session
{
    $visitor ??= makeDashboardVisitor();

    return Session::create(array_merge([
        'visitor_id' => $visitor->id,
        'started_at' => now(),
        'last_activity_at' => now(),
        'is_bot' => false,
        'pageview_count' => 1,
    ], $attrs));
}

function makeDashboardEvent(Session $session, EventType $type, array $attrs = []): Event
{
    return Event::create(array_merge([
        'session_id' => $session->id,
        'visitor_id' => $session->visitor_id,
        'occurred_at' => now()->subMinute(),
        'type' => $type,
    ], $attrs));
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
    $this->repository = new DashboardReadRepository;
    $this->period = Period::ofDays(30);
});

it('computes headline metrics with period-over-period deltas, excluding bots', function () {
    makeDashboardSession(['pageview_count' => 2]);
    makeDashboardSession(['pageview_count' => 2]);
    makeDashboardSession(['pageview_count' => 2]);
    makeDashboardSession(['pageview_count' => 1, 'started_at' => now()->subDays(40), 'last_activity_at' => now()->subDays(40)]);
    makeDashboardSession(['pageview_count' => 1, 'started_at' => now()->subDays(40), 'last_activity_at' => now()->subDays(40)]);
    makeDashboardSession(['pageview_count' => 9, 'is_bot' => true]);

    $headline = $this->repository->headline($this->period, null);

    expect($headline['sessions']->current)->toBe(3.0)
        ->and($headline['sessions']->previous)->toBe(2.0)
        ->and($headline['sessions']->changePercent())->toBe(50.0)
        ->and($headline['visitors']->current)->toBe(3.0)
        ->and($headline['pageviews']->current)->toBe(6.0)
        ->and($headline['pageviews']->previous)->toBe(2.0);
});

it('computes engagement metrics: average duration, pages per session and bounce rate', function () {
    makeDashboardSession(['pageview_count' => 2, 'started_at' => now()->subMinutes(5), 'last_activity_at' => now()->subMinutes(4)]);
    makeDashboardSession(['pageview_count' => 2, 'started_at' => now()->subMinutes(5), 'last_activity_at' => now()->subMinutes(4)]);
    makeDashboardSession(['pageview_count' => 1, 'started_at' => now()->subMinutes(3), 'last_activity_at' => now()->subMinutes(3)]);

    $headline = $this->repository->headline($this->period, null);

    expect((int) round($headline['avgSeconds']->current))->toBe(40)
        ->and(round($headline['pagesPerSession']->current, 2))->toBe(1.67)
        ->and(round($headline['bounceRate']->current, 1))->toBe(33.3);
});

it('narrows the metrics to a subject type', function () {
    $client = makeDashboardVisitor();
    makeDashboardSession(['subject_type' => 'client', 'subject_id' => 1], $client);
    makeDashboardSession(['subject_type' => 'client', 'subject_id' => 1], $client);
    makeDashboardSession(['subject_type' => 'lessor', 'subject_id' => 7]);

    $headline = $this->repository->headline($this->period, 'client');

    expect($headline['sessions']->current)->toBe(2.0)
        ->and($headline['visitors']->current)->toBe(1.0);
});

it('reports today and yesterday values for the headline metrics', function () {
    makeDashboardSession(['pageview_count' => 2]);
    makeDashboardSession(['pageview_count' => 2]);
    makeDashboardSession(['pageview_count' => 1, 'started_at' => now()->subDay(), 'last_activity_at' => now()->subDay()]);

    $spotlight = $this->repository->spotlight(null);

    expect($spotlight['sessions']['today'])->toBe(2.0)
        ->and($spotlight['visitors']['today'])->toBe(2.0)
        ->and($spotlight['sessions']['yesterday'])->toBe(1.0);
});

it('builds a continuous daily trend with zero-filled days', function () {
    $period = Period::ofDays(7);

    makeDashboardSession(['pageview_count' => 1]);
    makeDashboardSession(['pageview_count' => 1]);
    makeDashboardSession(['pageview_count' => 4, 'started_at' => now()->subDays(2)]);

    $trend = $this->repository->dailyTrend($period, null);

    expect($trend)->toHaveCount(7);

    $byDate = collect($trend)->keyBy(fn ($point) => $point->date->format('Y-m-d'));

    expect($byDate[now()->format('Y-m-d')]->sessions)->toBe(2)
        ->and($byDate[now()->subDays(2)->format('Y-m-d')]->pageviews)->toBe(4)
        ->and($byDate[now()->subDays(1)->format('Y-m-d')]->sessions)->toBe(0);
});

it('builds zero-filled daily sparkline series for the headline metrics', function () {
    $period = Period::ofDays(7);

    makeDashboardSession(['pageview_count' => 2]);
    makeDashboardSession(['pageview_count' => 2]);
    makeDashboardSession(['pageview_count' => 1, 'started_at' => now()->subDays(2), 'last_activity_at' => now()->subDays(2)]);

    $spark = $this->repository->headlineSparklines($period, null);

    expect($spark)->toHaveKeys(['visitors', 'sessions', 'avgSeconds', 'bounceRate'])
        ->and($spark['sessions'])->toHaveCount(7)
        ->and(end($spark['sessions']))->toBe(2.0)
        ->and(end($spark['visitors']))->toBe(2.0);
});

it('ranks the top sources with previous-period counts', function () {
    makeDashboardSession(['source' => 'google']);
    makeDashboardSession(['source' => 'google']);
    makeDashboardSession(['source' => 'facebook']);
    makeDashboardSession(['source' => 'google', 'started_at' => now()->subDays(40), 'last_activity_at' => now()->subDays(40)]);

    expect($this->repository->topSources($this->period, null))->toBe([
        ['label' => 'google', 'total' => 2, 'previous' => 1],
        ['label' => 'facebook', 'total' => 1, 'previous' => 0],
    ]);
});

it('ranks the top localities (country + city)', function () {
    makeDashboardSession(['country' => 'FR', 'city' => 'Paris']);
    makeDashboardSession(['country' => 'FR', 'city' => 'Paris']);
    makeDashboardSession(['country' => 'FR', 'city' => 'Lyon']);
    makeDashboardSession(['country' => 'CH', 'city' => 'Genève']);

    $localities = $this->repository->topLocalities($this->period, null);

    expect($localities)->toHaveCount(3)
        ->and($localities[0])->toBe(['country' => 'FR', 'city' => 'Paris', 'total' => 2, 'previous' => 0]);
});

it('ranks the most viewed pages from pageview events, excluding bot sessions', function () {
    $session = makeDashboardSession();
    makeDashboardEvent($session, EventType::Pageview, ['route' => 'home']);
    makeDashboardEvent($session, EventType::Pageview, ['route' => 'home']);
    makeDashboardEvent($session, EventType::Pageview, ['route' => 'catalog']);

    $bot = makeDashboardSession(['is_bot' => true]);
    makeDashboardEvent($bot, EventType::Pageview, ['route' => 'home']);

    expect($this->repository->topPages($this->period, null))->toBe([
        ['label' => 'home', 'total' => 2, 'previous' => 0],
        ['label' => 'catalog', 'total' => 1, 'previous' => 0],
    ]);
});

it('ranks the top clicks, preferring the visible text over the technical name', function () {
    $session = makeDashboardSession();
    makeDashboardEvent($session, EventType::Click, ['name' => 'cta.contact', 'target_text' => 'Nous contacter', 'route' => 'home']);
    makeDashboardEvent($session, EventType::Click, ['name' => 'cta.contact', 'target_text' => 'Nous contacter', 'route' => 'home']);
    makeDashboardEvent($session, EventType::Click, ['name' => 'auth.login', 'route' => 'client.login']);

    expect($this->repository->topClicks($this->period, null))->toBe([
        ['label' => 'Nous contacter', 'route' => 'home', 'total' => 2],
        ['label' => 'auth.login', 'route' => 'client.login', 'total' => 1],
    ]);
});

it('computes the share of new visitors in the period', function () {
    makeDashboardSession(['pageview_count' => 1]);

    $returning = makeDashboardVisitor();
    $returning->update(['first_seen_at' => now()->subMonths(3)]);
    makeDashboardSession([], $returning);

    expect($this->repository->newVisitorRate($this->period, null)->current)->toBe(50.0);
});

it('splits new and returning visitor counts', function () {
    makeDashboardSession();

    $returning = makeDashboardVisitor();
    $returning->update(['first_seen_at' => now()->subMonths(3)]);
    makeDashboardSession([], $returning);

    expect($this->repository->newVsReturning($this->period, null))->toBe(['new' => 1, 'returning' => 1]);
});

it('counts sessions by device type, busiest first', function () {
    makeDashboardSession(['device_type' => 'desktop']);
    makeDashboardSession(['device_type' => 'desktop']);
    makeDashboardSession(['device_type' => 'mobile']);
    makeDashboardSession(['device_type' => null]);

    expect($this->repository->sessionsByDevice($this->period, null))->toBe(['desktop' => 2, 'mobile' => 1]);
});

it('paginates sessions newest first, excluding bots, with filters', function () {
    foreach (range(1, 22) as $i) {
        makeDashboardSession(['city' => 'Paris', 'device_type' => 'desktop', 'started_at' => now()->subMinutes($i)]);
    }
    makeDashboardSession(['city' => 'Geneva', 'device_type' => 'mobile']);
    makeDashboardSession(['city' => 'Paris', 'is_bot' => true]);

    $all = $this->repository->paginateSessions($this->period, null, null, null, null, 20);
    $byCity = $this->repository->paginateSessions($this->period, null, 'Geneva', null, null, 20);
    $byDevice = $this->repository->paginateSessions($this->period, null, null, 'mobile', null, 20);

    expect($all->total())->toBe(23)
        ->and($all->count())->toBe(20)
        ->and($byCity->total())->toBe(1)
        ->and($byDevice->total())->toBe(1)
        ->and($byDevice->first()->city)->toBe('Geneva');
});
