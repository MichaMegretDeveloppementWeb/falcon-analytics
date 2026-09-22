<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Services\DailyCountArchiver;
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

    /** Marks every day up to yesterday as summarised, so the purge may proceed. */
    private function everythingIsArchived(): void
    {
        app(DailyCountArchiver::class)->run();
    }

    public function test_it_erases_anonymous_views_past_the_window_and_keeps_recent_ones(): void
    {
        $now = CarbonImmutable::parse('2026-07-03 12:00:00');
        CarbonImmutable::setTestNow($now);
        config(['analytics.retention_days' => 90]);

        $visitor = $this->visitor();
        $session = Session::create(['visitor_id' => $visitor->id, 'started_at' => $now, 'last_activity_at' => $now, 'is_bot' => false]);

        $old = Event::create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'occurred_at' => $now->subDays(100), 'type' => EventType::Pageview]);
        $recent = Event::create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'occurred_at' => $now->subDays(10), 'type' => EventType::Pageview]);

        $this->everythingIsArchived();
        $this->artisan('analytics:prune')->assertSuccessful();

        $this->assertFalse(Event::whereKey($old->id)->exists());
        $this->assertTrue(Event::whereKey($recent->id)->exists());
    }

    /**
     * What carries a name is never erased, whatever its age.
     *
     * **This is the rule the whole retention rests on.** A named event feeds
     * the events screen, the funnels and the marketing conversions, and no
     * daily count can stand in for it exactly — so it stays. Erasing one would
     * make a campaign read « 1 200 visiteurs, 0 conversion » without a word.
     */
    public function test_it_never_erases_what_carries_a_name(): void
    {
        $now = CarbonImmutable::parse('2026-07-03 12:00:00');
        CarbonImmutable::setTestNow($now);
        config(['analytics.retention_days' => 90]);

        $visitor = $this->visitor();
        $session = Session::create(['visitor_id' => $visitor->id, 'started_at' => $now, 'last_activity_at' => $now, 'is_bot' => false]);
        $ancient = $now->subDays(500);

        $named = Event::create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'occurred_at' => $ancient, 'type' => EventType::Custom, 'name' => 'commande.payee']);
        $namedClick = Event::create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'occurred_at' => $ancient, 'type' => EventType::Click, 'name' => 'devis.demande']);
        $anonymous = Event::create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'occurred_at' => $ancient, 'type' => EventType::Click, 'name' => '']);

        $this->everythingIsArchived();
        $this->artisan('analytics:prune')->assertSuccessful();

        $this->assertTrue(Event::whereKey($named->id)->exists(), 'A named event is the point of the whole design.');
        $this->assertTrue(Event::whereKey($namedClick->id)->exists(), 'A click can carry a name too.');
        $this->assertFalse(Event::whereKey($anonymous->id)->exists(), 'An empty name is not a name.');
    }

    /**
     * And the page views a declared funnel steps through stay as well.
     *
     * A funnel advances sequentially inside its window, which no daily count
     * can rebuild. Keeping the handful of routes it names is what keeps that
     * screen exact at any depth.
     */
    public function test_it_keeps_the_page_views_a_declared_funnel_needs(): void
    {
        $now = CarbonImmutable::parse('2026-07-03 12:00:00');
        CarbonImmutable::setTestNow($now);
        config([
            'analytics.retention_days' => 90,
            'analytics.funnels_path' => __DIR__.'/../Fixtures/analytics-funnels.php',
        ]);
        $this->app->forgetInstance(FunnelRegistry::class);

        $visitor = $this->visitor();
        $session = Session::create(['visitor_id' => $visitor->id, 'started_at' => $now, 'last_activity_at' => $now, 'is_bot' => false]);
        $ancient = $now->subDays(500);

        $steppedThrough = Event::create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'occurred_at' => $ancient, 'type' => EventType::Pageview, 'route' => $this->aFunnelRoute()]);
        $ordinary = Event::create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'occurred_at' => $ancient, 'type' => EventType::Pageview, 'route' => 'une.route.quelconque']);

        $this->everythingIsArchived();
        $this->artisan('analytics:prune')->assertSuccessful();

        $this->assertTrue(Event::whereKey($steppedThrough->id)->exists());
        $this->assertFalse(Event::whereKey($ordinary->id)->exists());
    }

    /** The first route any declared funnel steps through. */
    private function aFunnelRoute(): string
    {
        foreach (app(FunnelRegistry::class)->all() as $funnel) {
            foreach ($funnel->steps() as $step) {
                foreach ($step->routeNames() as $route) {
                    return $route;
                }
            }
        }

        $this->fail('The funnel fixture declares no route step, so this proves nothing.');
    }

    /**
     * Nothing is erased before the day has been summarised.
     *
     * **This is what makes a dead scheduler harmless.** On a shared host the
     * cron stops without a word; if the erasing kept going on its own, the
     * figures would leave with it. Here both halt together.
     */
    public function test_it_refuses_to_erase_a_day_that_was_never_summarised(): void
    {
        $now = CarbonImmutable::parse('2026-07-03 12:00:00');
        CarbonImmutable::setTestNow($now);
        config(['analytics.retention_days' => 90]);

        $visitor = $this->visitor();
        $session = Session::create(['visitor_id' => $visitor->id, 'started_at' => $now, 'last_activity_at' => $now, 'is_bot' => false]);
        Event::create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'occurred_at' => $now->subDays(500), 'type' => EventType::Pageview]);

        // No archiving at all: the scheduler never ran.
        $this->artisan('analytics:prune')->assertSuccessful();

        $this->assertSame(1, Event::count(), 'Nothing summarised, so nothing erased.');
    }

    public function test_it_erases_nothing_when_no_retention_is_set(): void
    {
        $now = CarbonImmutable::parse('2026-07-03 12:00:00');
        CarbonImmutable::setTestNow($now);
        config(['analytics.retention_days' => null]);

        $visitor = $this->visitor();
        $session = Session::create(['visitor_id' => $visitor->id, 'started_at' => $now, 'last_activity_at' => $now, 'is_bot' => false]);
        Event::create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'occurred_at' => $now->subDays(500), 'type' => EventType::Pageview]);

        $this->everythingIsArchived();
        $this->artisan('analytics:prune')->assertSuccessful();

        $this->assertSame(1, Event::count());
    }

    /**
     * Zero used to mean « keep everything », which is the opposite of what one
     * writes it for. It is now refused, and said.
     */
    public function test_it_refuses_a_retention_that_is_not_a_number_of_days(): void
    {
        config(['analytics.retention_days' => 0]);

        $this->artisan('analytics:prune')->assertFailed();
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

        // `ended_at` is set to the last activity plus the timeout (11:50 + 5
        // min), never to the time of the sweep.
        $freshIdle = $idle->fresh();
        $freshActive = $active->fresh();

        $this->assertNotNull($freshIdle);
        $this->assertNotNull($freshActive);

        $this->assertSame(
            $now->subMinutes(10)->addMinutes(5)->toDateTimeString(),
            $freshIdle->ended_at?->toDateTimeString(),
        );

        $this->assertNull($freshActive->ended_at);
    }

    public function test_the_sweep_costs_the_same_whether_it_closes_five_sessions_or_fifty(): void
    {
        $now = CarbonImmutable::parse('2026-07-03 12:00:00');
        CarbonImmutable::setTestNow($now);
        config(['analytics.session.timeout_minutes' => 5]);

        $visitor = $this->visitor();
        $idleSession = fn (): Session => Session::create([
            'visitor_id' => $visitor->id,
            'started_at' => $now->subMinutes(20),
            'last_activity_at' => $now->subMinutes(10),
            'is_bot' => false,
        ]);

        foreach (range(1, 5) as $ignored) {
            $idleSession();
        }

        $forFive = $this->statementsFor(fn () => $this->artisan('analytics:sweep')->run());

        foreach (range(1, 45) as $ignored) {
            $idleSession();
        }

        $forFifty = $this->statementsFor(fn () => $this->artisan('analytics:sweep')->run());

        $this->assertSame(
            $forFive['count'],
            $forFifty['count'],
            sprintf('The sweep writes per row: %d statements for 5 sessions, %d for 45.', $forFive['count'], $forFifty['count']),
        );

        $this->assertSame(0, Session::query()->whereNull('ended_at')->count(), 'every idle session is closed');
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

        $fresh = $session->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame($closedAt->toDateTimeString(), $fresh->ended_at?->toDateTimeString());
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
        $now = CarbonImmutable::parse('2026-07-03 12:00:00');
        CarbonImmutable::setTestNow($now);
        config(['analytics.retention_days' => 90]);

        // Something old enough to erase, and its day summarised — otherwise the
        // command stops on one of its two guards before ever reaching the table,
        // and the failure this watches could not happen.
        $visitor = $this->visitor();
        $session = Session::create(['visitor_id' => $visitor->id, 'started_at' => $now, 'last_activity_at' => $now, 'is_bot' => false]);
        Event::create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'occurred_at' => $now->subDays(100), 'type' => EventType::Pageview]);

        $this->everythingIsArchived();

        $this->withoutTable('falcon_analytics_events', function (): void {
            $this->artisan('analytics:prune')->assertFailed();
        });
    }
}
