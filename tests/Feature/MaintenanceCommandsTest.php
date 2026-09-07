<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

final class MaintenanceCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function visitor(): Visitor
    {
        return Visitor::create(['uuid' => 'u-'.uniqid(), 'first_seen_at' => now(), 'last_seen_at' => now()]);
    }

    public function test_it_prunes_events_older_than_the_retention_window_and_keeps_recent_ones(): void
    {
        $now = CarbonImmutable::parse('2026-07-03 12:00:00');
        CarbonImmutable::setTestNow($now);
        config(['analytics.retention_days' => 90]);

        $visitor = $this->visitor();
        $session = Session::create(['visitor_id' => $visitor->id, 'started_at' => $now, 'last_activity_at' => $now, 'is_bot' => false]);

        $old = Event::create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'occurred_at' => $now->subDays(100), 'type' => EventType::Pageview]);
        $recent = Event::create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'occurred_at' => $now->subDays(10), 'type' => EventType::Pageview]);

        $this->artisan('analytics:prune')->assertSuccessful();

        $this->assertFalse(Event::whereKey($old->id)->exists());
        $this->assertTrue(Event::whereKey($recent->id)->exists());
    }

    public function test_it_prunes_nothing_when_retention_is_disabled(): void
    {
        $now = CarbonImmutable::parse('2026-07-03 12:00:00');
        CarbonImmutable::setTestNow($now);
        config(['analytics.retention_days' => 0]);

        $visitor = $this->visitor();
        $session = Session::create(['visitor_id' => $visitor->id, 'started_at' => $now, 'last_activity_at' => $now, 'is_bot' => false]);
        Event::create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'occurred_at' => $now->subDays(500), 'type' => EventType::Pageview]);

        $this->artisan('analytics:prune')->assertSuccessful();

        $this->assertSame(1, Event::count());
    }

    public function test_it_closes_idle_sessions_with_a_deterministic_ended_at_and_leaves_active_ones_open(): void
    {
        $now = CarbonImmutable::parse('2026-07-03 12:00:00');
        CarbonImmutable::setTestNow($now);
        config(['analytics.session.timeout_minutes' => 5]);

        $visitor = $this->visitor();

        $idle = Session::create(['visitor_id' => $visitor->id, 'started_at' => $now->subMinutes(20), 'last_activity_at' => $now->subMinutes(10), 'is_bot' => false]);
        $active = Session::create(['visitor_id' => $visitor->id, 'started_at' => $now->subMinutes(3), 'last_activity_at' => $now->subMinutes(2), 'is_bot' => false]);

        $this->artisan('analytics:sweep')->assertSuccessful();

        // `ended_at` est posé à la dernière activité plus le délai (11:50 + 5
        // min), jamais à l'heure du balayage.
        $this->assertSame(
            $now->subMinutes(10)->addMinutes(5)->toDateTimeString(),
            $idle->fresh()->ended_at?->toDateTimeString(),
        );

        $this->assertNull($active->fresh()->ended_at);
    }

    public function test_it_does_not_re_close_an_already_closed_session(): void
    {
        $now = CarbonImmutable::parse('2026-07-03 12:00:00');
        CarbonImmutable::setTestNow($now);
        config(['analytics.session.timeout_minutes' => 5]);

        $visitor = $this->visitor();
        $closedAt = $now->subMinutes(30);

        $session = Session::create([
            'visitor_id' => $visitor->id,
            'started_at' => $now->subMinutes(40),
            'last_activity_at' => $now->subMinutes(35),
            'ended_at' => $closedAt,
            'is_bot' => false,
        ]);

        $this->artisan('analytics:sweep')->assertSuccessful();

        $this->assertSame($closedAt->toDateTimeString(), $session->fresh()->ended_at?->toDateTimeString());
    }

    public function test_it_schedules_the_sweep_every_five_minutes_and_the_prune_daily(): void
    {
        $events = Collection::make(app(Schedule::class)->events());

        $sweep = $events->first(fn ($e) => str_contains($e->command ?? '', 'analytics:sweep'));
        $prune = $events->first(fn ($e) => str_contains($e->command ?? '', 'analytics:prune'));

        $this->assertNotNull($sweep);
        $this->assertSame('*/5 * * * *', $sweep->expression);
        $this->assertNotNull($prune);
        $this->assertSame('30 3 * * *', $prune->expression);
    }

    public function test_it_fails_cleanly_when_the_sweep_write_blows_up(): void
    {
        $this->withoutTable('falcon_analytics_sessions', function (): void {
            $this->artisan('analytics:sweep')->assertFailed();
        });
    }

    public function test_it_fails_cleanly_when_the_prune_delete_blows_up(): void
    {
        config(['analytics.retention_days' => 90]);

        $this->withoutTable('falcon_analytics_events', function (): void {
            $this->artisan('analytics:prune')->assertFailed();
        });
    }
}
