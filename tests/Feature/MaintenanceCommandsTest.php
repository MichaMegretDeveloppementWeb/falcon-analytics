<?php

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function maintenanceVisitor(): Visitor
{
    return Visitor::create(['uuid' => 'u-'.uniqid(), 'first_seen_at' => now(), 'last_seen_at' => now()]);
}

it('prunes events older than the retention window and keeps recent ones', function () {
    $now = CarbonImmutable::parse('2026-07-03 12:00:00');
    CarbonImmutable::setTestNow($now);
    config(['analytics.retention_days' => 90]);

    $visitor = maintenanceVisitor();
    $session = Session::create(['visitor_id' => $visitor->id, 'started_at' => $now, 'last_activity_at' => $now, 'is_bot' => false]);

    $old = Event::create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'occurred_at' => $now->subDays(100), 'type' => EventType::Pageview]);
    $recent = Event::create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'occurred_at' => $now->subDays(10), 'type' => EventType::Pageview]);

    $this->artisan('analytics:prune')->assertSuccessful();

    expect(Event::whereKey($old->id)->exists())->toBeFalse()
        ->and(Event::whereKey($recent->id)->exists())->toBeTrue();
});

it('prunes nothing when retention is disabled', function () {
    $now = CarbonImmutable::parse('2026-07-03 12:00:00');
    CarbonImmutable::setTestNow($now);
    config(['analytics.retention_days' => 0]);

    $visitor = maintenanceVisitor();
    $session = Session::create(['visitor_id' => $visitor->id, 'started_at' => $now, 'last_activity_at' => $now, 'is_bot' => false]);
    Event::create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'occurred_at' => $now->subDays(500), 'type' => EventType::Pageview]);

    $this->artisan('analytics:prune')->assertSuccessful();

    expect(Event::count())->toBe(1);
});

it('closes idle sessions with a deterministic ended_at and leaves active ones open', function () {
    $now = CarbonImmutable::parse('2026-07-03 12:00:00');
    CarbonImmutable::setTestNow($now);
    config(['analytics.session.timeout_minutes' => 5]);

    $visitor = maintenanceVisitor();

    $idle = Session::create(['visitor_id' => $visitor->id, 'started_at' => $now->subMinutes(20), 'last_activity_at' => $now->subMinutes(10), 'is_bot' => false]);
    $active = Session::create(['visitor_id' => $visitor->id, 'started_at' => $now->subMinutes(3), 'last_activity_at' => $now->subMinutes(2), 'is_bot' => false]);

    $this->artisan('analytics:sweep')->assertSuccessful();

    // ended_at is stamped at last_activity + timeout (11:50 + 5 min), never the sweep time.
    expect($idle->fresh()->ended_at?->toDateTimeString())->toBe($now->subMinutes(10)->addMinutes(5)->toDateTimeString())
        ->and($active->fresh()->ended_at)->toBeNull();
});

it('does not re-close an already closed session', function () {
    $now = CarbonImmutable::parse('2026-07-03 12:00:00');
    CarbonImmutable::setTestNow($now);
    config(['analytics.session.timeout_minutes' => 5]);

    $visitor = maintenanceVisitor();
    $closedAt = $now->subMinutes(30);
    $session = Session::create(['visitor_id' => $visitor->id, 'started_at' => $now->subMinutes(40), 'last_activity_at' => $now->subMinutes(35), 'ended_at' => $closedAt, 'is_bot' => false]);

    $this->artisan('analytics:sweep')->assertSuccessful();

    expect($session->fresh()->ended_at?->toDateTimeString())->toBe($closedAt->toDateTimeString());
});

it('schedules the sweep every five minutes and the prune daily', function () {
    $events = collect(app(Schedule::class)->events());

    $sweep = $events->first(fn ($e) => str_contains($e->command ?? '', 'analytics:sweep'));
    $prune = $events->first(fn ($e) => str_contains($e->command ?? '', 'analytics:prune'));

    expect($sweep)->not->toBeNull()
        ->and($sweep->expression)->toBe('*/5 * * * *')
        ->and($prune)->not->toBeNull()
        ->and($prune->expression)->toBe('30 3 * * *');
});

it('fails cleanly when the sweep write blows up', function () {
    // Force a real write failure: the sessions table is gone.
    Schema::drop('falcon_analytics_events');
    Schema::drop('falcon_analytics_sessions');

    $this->artisan('analytics:sweep')->assertFailed();
});

it('fails cleanly when the prune delete blows up', function () {
    config(['analytics.retention_days' => 90]);

    // Force a real delete failure: the events table is gone.
    Schema::drop('falcon_analytics_events');

    $this->artisan('analytics:prune')->assertFailed();
});
