<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\DailyArchive;
use Falcon\Analytics\Models\DailyCount;
use Falcon\Analytics\Models\Event as AnalyticsEvent;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The maintenance has two ways in, and they must be the same way.
 *
 * The scheduler is the normal one. The second exists because **a scheduler on
 * shared hosting stops without a word**: the summarising stops with it, the
 * erasing stops too — nothing is lost, by design — but the summaries fall
 * behind and no screen says so. So opening a screen catches the backlog up.
 *
 * **Both run the same two commands, in the same order**, which is what this
 * essay holds. A second path with its own logic would be a second set of guards
 * to keep in step, and the one that runs least often is the one that would rot.
 *
 * Written 2026-09-13, with the summaries.
 */
final class TheMaintenanceRunsBothWaysTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
        Cache::clear();
    }

    protected function tearDown(): void
    {
        Cache::clear();

        parent::tearDown();
    }

    /** Something old enough for there to be days worth summarising. */
    private function aVisitOn(string $day): void
    {
        $visitor = Visitor::create(['uuid' => (string) Str::uuid(), 'first_seen_at' => now(), 'last_seen_at' => now()]);

        $session = Session::create([
            'visitor_id' => $visitor->id,
            'started_at' => CarbonImmutable::parse($day),
            'last_activity_at' => CarbonImmutable::parse($day),
            'is_bot' => false,
        ]);

        AnalyticsEvent::create([
            'session_id' => $session->id,
            'visitor_id' => $visitor->id,
            'type' => EventType::Pageview,
            'url' => 'https://exemple.fr/',
            'occurred_at' => CarbonImmutable::parse($day)->setTime(10, 0),
        ]);
    }

    private function anAdmin(): TestAdmin
    {
        return TestAdmin::create(['email' => 'admin@example.test']);
    }

    /** What the scheduler holds for a command, whatever the clock says. */
    private function scheduled(string $command): Event
    {
        foreach (app(Schedule::class)->events() as $event) {
            if (str_contains((string) $event->command, $command)) {
                return $event;
            }
        }

        $this->fail("The scheduler holds nothing for {$command}.");
    }

    /**
     * The scheduler's way · asked of the scheduler itself, at the hour of a
     * cron tick.
     *
     * **Not through `schedule:run`**, and the reason is worth writing down: it
     * launches each command as its own process, which boots its own
     * application and would never see this one's database. What a cron
     * genuinely decides is whether an event is due at the minute it wakes, and
     * that is asked here, minute by minute.
     *
     * Summarising at 03:00 and erasing at 03:30, in that order · the erasing
     * refuses a day the summarising has not treated, so the order is the
     * guarantee and not a detail.
     */
    public function test_a_cron_tick_at_three_finds_the_summarising_due(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 03:00:00'));
        $this->assertTrue($this->scheduled('analytics:archive')->isDue(app()));
        $this->assertFalse($this->scheduled('analytics:prune')->isDue(app()), 'Erasing waits its half hour.');

        $this->travelTo(CarbonImmutable::parse('2026-06-15 03:30:00'));
        $this->assertTrue($this->scheduled('analytics:prune')->isDue(app()));
        $this->assertFalse($this->scheduled('analytics:archive')->isDue(app()));
    }

    /**
     * And at any other minute a tick finds nothing to do, which is the other
     * half of being scheduled · summarising on every tick would rewrite the
     * same days all day long.
     */
    public function test_a_cron_tick_at_any_other_minute_finds_nothing_due(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 11:00:00'));

        $this->assertFalse($this->scheduled('analytics:archive')->isDue(app()));
        $this->assertFalse($this->scheduled('analytics:prune')->isDue(app()));
    }

    /**
     * And what a tick at 03:00 then 03:30 actually does, run in that order.
     *
     * The commands themselves, which is what the scheduler launches · this is
     * the cron's effect without the cron's process.
     */
    public function test_the_two_commands_a_cron_launches_summarise_and_erase(): void
    {
        $this->aVisitOn('2026-06-12');

        $this->assertSame(0, DailyArchive::query()->count(), 'Nothing summarised yet.');

        $this->artisan('analytics:archive')->assertSuccessful();
        $this->artisan('analytics:prune')->assertSuccessful();

        $this->assertTrue(DailyArchive::query()->where('day', '2026-06-12')->exists());
    }

    /** Opening a screen catches the backlog up, after the page has gone. */
    public function test_opening_a_screen_catches_the_backlog_up(): void
    {
        $this->aVisitOn('2026-06-12');

        $this->actingAs($this->anAdmin(), 'admin')
            ->get(route('analytics.admin.overview'))
            ->assertSuccessful();

        $this->assertTrue(
            DailyArchive::query()->where('day', '2026-06-12')->exists(),
            'A screen was opened and the summarising had not run: it should have caught up.',
        );
    }

    /** A marketing screen too · every analytics screen is a way in. */
    public function test_a_marketing_screen_is_a_way_in_as_well(): void
    {
        $this->aVisitOn('2026-06-12');

        $this->actingAs($this->anAdmin(), 'admin')
            ->get(route('analytics.admin.marketing.dashboard'))
            ->assertSuccessful();

        $this->assertTrue(DailyArchive::query()->where('day', '2026-06-12')->exists());
    }

    /**
     * Once per interval, whoever opens a screen.
     *
     * The failure this guards is not a wrong figure but a slow site · a
     * summarising started on every page load would run on every click of every
     * administrator.
     */
    public function test_it_runs_once_per_interval_and_not_on_every_page(): void
    {
        $this->aVisitOn('2026-06-12');

        $admin = $this->anAdmin();

        $this->actingAs($admin, 'admin')->get(route('analytics.admin.overview'))->assertSuccessful();

        $firstRun = DailyArchive::query()->where('day', '2026-06-12')->firstOrFail()->archived_at;

        // A second visit, a minute later: the interval has not passed.
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:01:00'));
        $this->actingAs($admin, 'admin')->get(route('analytics.admin.overview'))->assertSuccessful();

        $this->assertEquals(
            $firstRun,
            DailyArchive::query()->where('day', '2026-06-12')->firstOrFail()->archived_at,
            'It ran a second time inside its own interval.',
        );
    }

    /** And a host that trusts its scheduler can switch it off. */
    public function test_it_can_be_switched_off(): void
    {
        config(['analytics.internal.maintenance.on_screen_load' => false]);
        $this->aVisitOn('2026-06-12');

        $this->actingAs($this->anAdmin(), 'admin')
            ->get(route('analytics.admin.overview'))
            ->assertSuccessful();

        $this->assertSame(0, DailyArchive::query()->count());
    }

    /**
     * Two administrators at once, and only one run.
     *
     * The lock is taken for the length of a run. Holding it from outside is
     * what a second request in flight looks like, and the visit that meets it
     * has to do nothing rather than wait or duplicate.
     */
    public function test_two_administrators_at_once_do_not_both_run_it(): void
    {
        $this->aVisitOn('2026-06-12');

        $held = Cache::lock('falcon-analytics:maintenance:running', 300);
        $this->assertTrue($held->get(), 'The lock has to be free for this to mean anything.');

        try {
            $this->actingAs($this->anAdmin(), 'admin')
                ->get(route('analytics.admin.overview'))
                ->assertSuccessful();

            $this->assertSame(0, DailyArchive::query()->count(), 'Someone else was already running it.');
        } finally {
            $held->release();
        }
    }

    /**
     * A visit catches up a few days at a time, not all of them.
     *
     * A year of backlog summarised inside one page's terminate would hold a
     * worker for as long as it takes; several visits share the work instead.
     */
    public function test_a_visit_catches_up_a_bounded_number_of_days(): void
    {
        config(['analytics.internal.maintenance.days_per_run' => 2]);
        $this->aVisitOn('2026-06-01');

        $this->actingAs($this->anAdmin(), 'admin')
            ->get(route('analytics.admin.overview'))
            ->assertSuccessful();

        $this->assertSame(2, DailyArchive::query()->count(), 'It should stop after its two days.');
    }

    /**
     * The register keeps the hour at which it acted, not just the date.
     *
     * **Read back through the model**, which is the point · the two stamps are
     * written as plain strings by the archiving and the erasing, so a model
     * that reads them badly would be discovered only by whoever first asked
     * « quand ce jour a-t-il été effacé ? » — a question one asks precisely
     * when something has gone wrong, and gets a wrong answer to.
     *
     * A single `$dateFormat` on the model cannot serve both a date column and
     * two timestamps, and the shorter of the two silently wins.
     */
    public function test_the_register_keeps_the_hour_it_acted_at(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 03:30:00'));
        $this->aVisitOn('2026-01-05');

        $this->artisan('analytics:archive')->assertSuccessful();
        $this->artisan('analytics:prune')->assertSuccessful();

        $row = DailyArchive::query()->where('day', '2026-01-05')->firstOrFail();

        $this->assertSame('2026-01-05', $row->day->toDateString(), 'The day itself is a date, and stays one.');
        $this->assertSame('2026-06-15 03:30:00', $row->archived_at->toDateTimeString());
        $this->assertNotNull($row->pruned_at, 'That day is old enough to have been erased.');
        $this->assertSame('2026-06-15 03:30:00', $row->pruned_at->toDateTimeString());
    }

    /**
     * And it keeps it when the register is written through the model too.
     *
     * The maintenance writes plain statements, so the model's own conversions
     * never run today — which is exactly why this is worth holding. **The
     * measured loss came from there** · one `$dateFormat` cannot serve a date
     * key and two timestamps, and the shorter of the two wins in silence. The
     * next hand to reach for `DailyArchive::create()` would have recorded every
     * run at midnight and had no way of noticing.
     */
    public function test_the_register_keeps_the_hour_when_written_through_the_model(): void
    {
        DailyArchive::create([
            'day' => CarbonImmutable::parse('2026-01-05'),
            'archived_at' => CarbonImmutable::parse('2026-06-15 03:30:00'),
            'pruned_at' => CarbonImmutable::parse('2026-06-15 03:31:00'),
        ]);

        $written = DB::table(DailyArchive::TABLE)->where('day', '2026-01-05')->first();

        $this->assertNotNull($written, 'The key has to be written as a plain date, or nothing finds it again.');
        $this->assertSame('2026-06-15 03:30:00', (string) $written->archived_at);
        $this->assertSame('2026-06-15 03:31:00', (string) $written->pruned_at);
    }

    /** The counters' key goes the same way, being read back by the same date. */
    public function test_a_counter_written_through_the_model_keeps_a_findable_key(): void
    {
        DailyCount::create([
            'day' => CarbonImmutable::parse('2026-01-05'),
            'kind' => DailyCount::KIND_PAGE,
            'signature' => DailyCount::signature(DailyCount::KIND_PAGE, 'https://exemple.fr/', null, null),
            'label' => 'https://exemple.fr/',
            'route' => null,
            'subject_type' => null,
            'total' => 3,
        ]);

        $written = DB::table(DailyCount::TABLE)->where('day', '2026-01-05')->first();

        $this->assertNotNull($written);
        $this->assertSame(3, (int) $written->total);
    }
}
