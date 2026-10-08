<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Actions\ForgetVisitorAction;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Facades\Analytics;
use Falcon\Analytics\Models\DailyArchive;
use Falcon\Analytics\Models\DailyCount;
use Falcon\Analytics\Models\DailyEventTotal;
use Falcon\Analytics\Models\DailySessionTotal;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\Dashboard\EngagementReadRepository;
use Falcon\Analytics\Repositories\Dashboard\EventReadRepository;
use Falcon\Analytics\Repositories\Dashboard\OverviewReadRepository;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * An erasure takes a person's rows from days the screens still read from
 * their rows · those days are summarised again in the same gesture, so a
 * figure reads the same before and after the line passes them. Days already
 * read from their totals keep them, the person counted there naming no one.
 */
final class AnErasureLeavesNoFigureToJumpTest extends TestCase
{
    use RefreshDatabase;

    private const CABINET = ['type' => 'client', 'id' => 7];

    private Period $reachingBack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
        config([
            'analytics.retention_days' => 30,
            'analytics.session_retention_days' => 60,
            'analytics.event_retention_days' => 60,
            'analytics.events_path' => __DIR__.'/../Fixtures/analytics-events.php',
        ]);
        $this->app->forgetInstance(EventRegistry::class);

        $this->reachingBack = new Period(CarbonImmutable::parse('2026-05-01')->startOfDay(), CarbonImmutable::now(), 46);
    }

    /** The proof · a recent day, read from its rows after the erasure, reads the same once read from its totals. */
    public function test_a_day_reads_the_same_once_the_line_passes_it(): void
    {
        $day = CarbonImmutable::now()->subDays(10)->setTime(9, 0);
        $this->aDayOfTraffic($day);
        $this->artisan('analytics:archive')->assertSuccessful();

        Analytics::forgetSubject(self::CABINET['type'], self::CABINET['id']);
        $before = $this->everythingThatAddsUp();

        $this->theLinePasses($day->addDay()->startOfDay());

        $this->assertSame(0, Session::query()->where('started_at', '<', $day->addDay()->startOfDay())->count(), 'Nothing was erased, so nothing is being proved.');

        foreach ($before as $block => $figures) {
            $this->assertEquals($figures, $this->everythingThatAddsUp()[$block], "Le bloc « {$block} » a bougé quand la ligne est passée.");
        }
    }

    /** The page views of a recent day no longer count the person · they read from their totals as soon as the day is summarised. */
    public function test_the_pages_of_a_recent_day_no_longer_count_the_person(): void
    {
        $day = CarbonImmutable::now()->subDays(10)->setTime(9, 0);
        $this->aDayOfTraffic($day);
        $this->artisan('analytics:archive')->assertSuccessful();

        $this->assertContains('/espace-cabinet', $this->pagesRead(), 'The cabinet\'s page has to be counted first.');

        Analytics::forgetSubject(self::CABINET['type'], self::CABINET['id']);

        $this->assertNotContains('/espace-cabinet', $this->pagesRead());
        $this->assertContains('/tarifs', $this->pagesRead(), 'The others\' pages stay.');
    }

    /** A day older than the page views kept keeps its page totals · its rows are gone, and a summary made again would count less. */
    public function test_a_day_past_the_page_views_kept_keeps_its_page_totals(): void
    {
        $day = CarbonImmutable::now()->subDays(40)->setTime(9, 0);
        $this->aDayOfTraffic($day);
        $this->artisan('analytics:archive')->assertSuccessful();
        $this->artisan('analytics:prune')->assertSuccessful();
        $totals = $this->totalsOf(DailyCount::class, $day);

        Analytics::forgetSubject(self::CABINET['type'], self::CABINET['id']);

        $this->assertNotSame([], $totals);
        $this->assertSame($totals, $this->totalsOf(DailyCount::class, $day));
    }

    /** Named events kept shorter than sessions · the day's sessions are summarised again, its named events keep their totals. */
    public function test_each_table_of_totals_is_summarised_again_only_where_its_rows_are_whole(): void
    {
        config(['analytics.event_retention_days' => 30]);
        $day = CarbonImmutable::now()->subDays(40)->setTime(9, 0);
        $this->aDayOfTraffic($day);
        $this->artisan('analytics:archive')->assertSuccessful();
        $this->artisan('analytics:prune')->assertSuccessful();
        $events = $this->totalsOf(DailyEventTotal::class, $day);

        Analytics::forgetSubject(self::CABINET['type'], self::CABINET['id']);

        $this->assertNotSame([], $events);
        $this->assertSame($events, $this->totalsOf(DailyEventTotal::class, $day), 'The named events of the day are erased · their totals stay.');
        $this->assertSame(2, $this->sessionsSummarisedOn($day), 'The two others\' sessions, the cabinet\'s gone.');
    }

    /** A day whose sessions the purge has begun erasing keeps its totals, even when one of the person's sessions outlived it. */
    public function test_a_day_past_the_sessions_kept_keeps_its_totals(): void
    {
        $day = CarbonImmutable::now()->subDays(61)->setTime(23, 50);
        $this->aDayOfTraffic($day->setTime(9, 0));
        $lateNight = $this->visit($this->profile(self::CABINET), self::CABINET, $day, '/espace-cabinet');
        $lateNight->update(['last_activity_at' => $day->addMinutes(20)]);
        $this->artisan('analytics:archive')->assertSuccessful();
        $this->artisan('analytics:prune')->assertSuccessful();

        $this->assertTrue(Session::query()->whereKey($lateNight->id)->exists(), 'The session past midnight has to outlive its day.');
        $sessions = $this->totalsOf(DailySessionTotal::class, $day);

        Analytics::forgetSubject(self::CABINET['type'], self::CABINET['id']);

        $this->assertNotSame([], $sessions);
        $this->assertSame($sessions, $this->totalsOf(DailySessionTotal::class, $day));
    }

    /** Only the days the person came on are summarised again · another day keeps its totals as they are. */
    public function test_a_day_the_person_never_came_on_is_left_alone(): void
    {
        $theirs = CarbonImmutable::now()->subDays(10)->setTime(9, 0);
        $another = CarbonImmutable::now()->subDays(12)->setTime(9, 0);
        $this->aDayOfTraffic($theirs);
        $this->visit($this->profile(null), null, $another, '/tarifs');
        $this->artisan('analytics:archive')->assertSuccessful();
        DailySessionTotal::query()->where('day', $another->toDateString())->update(['sessions' => 999]);

        Analytics::forgetSubject(self::CABINET['type'], self::CABINET['id']);

        $this->assertSame(999, DailySessionTotal::query()->where('day', $another->toDateString())->max('sessions'));
    }

    /** A day not yet summarised is not summarised by the erasure · the nightly pass does it, from rows that no longer hold the person. */
    public function test_a_day_still_open_is_not_summarised(): void
    {
        $today = CarbonImmutable::now()->setTime(9, 0);
        $this->aDayOfTraffic($today);

        Analytics::forgetSubject(self::CABINET['type'], self::CABINET['id']);

        $this->assertFalse(DailyArchive::query()->where('day', $today->toDateString())->exists());
        $this->assertSame(0, DailySessionTotal::query()->where('day', $today->toDateString())->count());
        $this->assertSame(0, DailyCount::query()->where('day', $today->toDateString())->count());
    }

    /**
     * A day whose page views are summarised and whose sessions wait for their
     * first summary · what a host has after an update, before the first pass.
     * The erasure summarises the page views again, and leaves the sessions to
     * that pass.
     */
    public function test_a_day_whose_sessions_wait_for_their_first_summary_is_left_to_it(): void
    {
        $day = CarbonImmutable::now()->subDays(10)->setTime(9, 0);
        $this->aDayOfTraffic($day);
        $this->artisan('analytics:archive')->assertSuccessful();
        DailySessionTotal::query()->delete();
        DailyEventTotal::query()->delete();
        DailyArchive::query()->update(['detail_archived_at' => null]);

        Analytics::forgetSubject(self::CABINET['type'], self::CABINET['id']);

        $this->assertSame(0, DailySessionTotal::query()->count());
        $this->assertSame(0, DailyEventTotal::query()->count());
        $this->assertNull(DailyArchive::query()->where('day', $day->toDateString())->value('detail_archived_at'));
        $this->assertNotContains('/espace-cabinet', $this->pagesRead());
    }

    /**
     * Every day the person's rows sat on is summarised again · not only the
     * day their visits began.
     */
    #[DataProvider('rowsOnAnotherDay')]
    public function test_every_day_the_persons_rows_sat_on_is_summarised_again(string $case): void
    {
        $night = CarbonImmutable::now()->subDays(10)->setTime(23, 50);
        $nextMorning = $night->addMinutes(15);
        $this->visit($this->profile(null), null, $nextMorning->setTime(9, 0), '/tarifs');

        match ($case) {
            'a click left in someone else\'s visit' => $this->pageIn(
                $this->visit($this->profile(['type' => 'client', 'id' => 8]), ['type' => 'client', 'id' => 8], $nextMorning, '/tarifs'),
                $nextMorning,
                self::CABINET,
            ),
            'their visit before signing in, past midnight' => $this->pageIn(
                $this->visit($this->profile(self::CABINET), null, $night, '/tarifs'),
                $nextMorning,
                null,
            ),
            'their visit on a shared browser, past midnight' => $this->pageIn(
                $this->visit($this->profile(['type' => 'client', 'id' => 8]), self::CABINET, $night, '/tarifs'),
                $nextMorning,
                null,
            ),
            default => self::fail("No such case · {$case}."),
        };

        $this->artisan('analytics:archive')->assertSuccessful();
        $this->assertContains('/espace-cabinet', $this->pagesRead(), 'The cabinet\'s page has to be counted first.');

        Analytics::forgetSubject(self::CABINET['type'], self::CABINET['id']);

        $this->assertNotContains('/espace-cabinet', $this->pagesRead());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rowsOnAnotherDay(): array
    {
        return [
            'a click left in someone else\'s visit' => ['a click left in someone else\'s visit'],
            'their visit before signing in, past midnight' => ['their visit before signing in, past midnight'],
            'their visit on a shared browser, past midnight' => ['their visit on a shared browser, past midnight'],
        ];
    }

    /**
     * An old visit whose events are already erased · the day it began is the
     * only thing that names the totals it went into.
     */
    #[DataProvider('visitsWithoutEvents')]
    public function test_the_day_an_old_visit_began_is_summarised_again(string $case): void
    {
        config(['analytics.event_retention_days' => 30]);
        $day = CarbonImmutable::now()->subDays(40)->setTime(9, 0);
        $this->visit($this->profile(null), null, $day, '/tarifs');

        match ($case) {
            'their visit on a shared browser' => $this->visit($this->profile(['type' => 'client', 'id' => 8]), self::CABINET, $day->addMinutes(5), '/tarifs'),
            'their visit before signing in' => $this->visit($this->profile(self::CABINET), null, $day->addMinutes(5), '/tarifs'),
            default => self::fail("No such case · {$case}."),
        };

        $this->artisan('analytics:archive')->assertSuccessful();
        $this->artisan('analytics:prune')->assertSuccessful();
        $this->assertSame(0, Event::query()->where('occurred_at', '<', $day->addDay())->count(), 'The events have to be gone, or they would name the day.');
        $this->assertSame(2, $this->sessionsSummarisedOn($day));

        Analytics::forgetSubject(self::CABINET['type'], self::CABINET['id']);

        $this->assertSame(1, $this->sessionsSummarisedOn($day));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function visitsWithoutEvents(): array
    {
        return [
            'their visit on a shared browser' => ['their visit on a shared browser'],
            'their visit before signing in' => ['their visit before signing in'],
        ];
    }

    /** « Oublier ce visiteur » summarises again every day the visitor's rows sat on, past midnight included. */
    public function test_forgetting_a_visitor_reaches_the_day_after_midnight(): void
    {
        $night = CarbonImmutable::now()->subDays(10)->setTime(23, 50);
        $nextMorning = $night->addMinutes(15);
        $this->visit($this->profile(null), null, $nextMorning->setTime(9, 0), '/tarifs');
        $visitor = $this->profile(null);
        $this->pageIn($this->visit($visitor, null, $night, '/tarifs'), $nextMorning, null);
        $this->artisan('analytics:archive')->assertSuccessful();
        $this->assertContains('/espace-cabinet', $this->pagesRead(), 'The page has to be counted first.');

        $this->app->make(ForgetVisitorAction::class)->execute($visitor);

        $this->assertNotContains('/espace-cabinet', $this->pagesRead());
    }

    /** « Oublier ce visiteur » keeps the same promise. */
    public function test_forgetting_a_visitor_leaves_no_figure_to_jump_either(): void
    {
        $day = CarbonImmutable::now()->subDays(10)->setTime(9, 0);
        $this->aDayOfTraffic($day);
        $this->artisan('analytics:archive')->assertSuccessful();

        $this->app->make(ForgetVisitorAction::class)->execute(Visitor::query()->where('subject_id', self::CABINET['id'])->sole());
        $before = $this->everythingThatAddsUp();

        $this->theLinePasses($day->addDay()->startOfDay());

        foreach ($before as $block => $figures) {
            $this->assertEquals($figures, $this->everythingThatAddsUp()[$block], "Le bloc « {$block} » a bougé quand la ligne est passée.");
        }

        $this->assertNotContains('/espace-cabinet', $this->pagesRead());
    }

    /**
     * A day of traffic · the cabinet's visit, two others', each with its page
     * views and a named event.
     */
    private function aDayOfTraffic(CarbonImmutable $at): void
    {
        $this->visit($this->profile(self::CABINET), self::CABINET, $at, '/espace-cabinet');
        $this->visit($this->profile(null), null, $at->addMinutes(5), '/tarifs');
        $this->visit($this->profile(null), null, $at->addMinutes(10), '/tarifs');
    }

    /**
     * @param  array{type: string, id: int}|null  $subject
     */
    private function profile(?array $subject): Visitor
    {
        return Visitor::factory()->create([
            'first_seen_at' => CarbonImmutable::now()->subDays(90),
            'last_seen_at' => CarbonImmutable::now(),
            'subject_type' => $subject['type'] ?? null,
            'subject_id' => $subject['id'] ?? null,
            'session_count' => 1,
        ]);
    }

    /**
     * A visit of two page views and one named event, all carrying its subject.
     *
     * @param  array{type: string, id: int}|null  $subject
     */
    private function visit(Visitor $on, ?array $subject, CarbonImmutable $at, string $page): Session
    {
        $session = Session::factory()->for($on)->create([
            'started_at' => $at,
            'last_activity_at' => $at->addMinutes(3),
            'subject_type' => $subject['type'] ?? null,
            'subject_id' => $subject['id'] ?? null,
            'pageview_count' => 2,
            'event_count' => 3,
        ]);

        $carries = ['subject_type' => $subject['type'] ?? null, 'subject_id' => $subject['id'] ?? null];

        Event::factory()->for($session)->count(2)->create(['occurred_at' => $at, 'url' => 'https://exemple.fr'.$page, 'page' => $page, ...$carries]);
        Event::factory()->for($session)->custom('sample.action')->create(['occurred_at' => $at, ...$carries]);

        return $session;
    }

    /**
     * The cabinet's page, viewed in a visit at a given moment.
     *
     * @param  array{type: string, id: int}|null  $carrying
     */
    private function pageIn(Session $visit, CarbonImmutable $at, ?array $carrying): void
    {
        Event::factory()->for($visit)->create([
            'occurred_at' => $at,
            'url' => 'https://exemple.fr/espace-cabinet',
            'page' => '/espace-cabinet',
            'subject_type' => $carrying['type'] ?? null,
            'subject_id' => $carrying['id'] ?? null,
        ]);
    }

    /** As the purge does it · the days marked, then their sessions and named events deleted. */
    private function theLinePasses(CarbonImmutable $cutoff): void
    {
        DailyArchive::query()->where('day', '<', $cutoff->toDateString())->update([
            'sessions_pruned_at' => CarbonImmutable::now(),
            'events_pruned_at' => CarbonImmutable::now(),
        ]);

        Session::query()->where('last_activity_at', '<', $cutoff)->delete();
    }

    /**
     * What adds up over the period, as the screens read it · the people
     * counted aside, since a figure of people says it is unavailable past the line.
     *
     * @return array<string, mixed>
     */
    private function everythingThatAddsUp(): array
    {
        $this->app->forgetScopedInstances();
        $engagement = $this->app->make(EngagementReadRepository::class);
        $overview = $this->app->make(OverviewReadRepository::class);
        $events = $this->app->make(EventReadRepository::class);
        $registry = $this->app->make(EventRegistry::class);

        $withoutPeople = fn (array $rows): array => array_map(function (array $row): array {
            unset($row['visitors']);

            return $row;
        }, $rows);

        return [
            'en-tête' => array_diff_key($engagement->headlineCounts($this->reachingBack, null), ['visitors' => true]),
            'en-tête, pour un client' => array_diff_key($engagement->headlineCounts($this->reachingBack, 'client'), ['visitors' => true]),
            'tendance' => $overview->trendRows($this->reachingBack, null),
            'appareils' => $overview->sessionsByDevice($this->reachingBack, null),
            'sources' => $overview->topSources($this->reachingBack, null, 20),
            'événements' => $withoutPeople($events->eventBreakdown($this->reachingBack, null, $registry)),
            'événements par jour' => $events->daily($this->reachingBack, null, $registry),
        ];
    }

    /**
     * The pages the screen ranks over the period.
     *
     * @return list<string>
     */
    private function pagesRead(): array
    {
        $this->app->forgetScopedInstances();

        return array_column($this->app->make(OverviewReadRepository::class)->topPages($this->reachingBack, null, 20), 'label');
    }

    /**
     * The totals of one day in one table, in a stable order.
     *
     * @param  class-string<DailyCount|DailySessionTotal|DailyEventTotal>  $table
     * @return list<array<string, mixed>>
     */
    private function totalsOf(string $table, CarbonImmutable $day): array
    {
        return array_values($table::query()
            ->where('day', $day->toDateString())
            ->orderBy('signature')
            ->get()
            ->map(fn ($row): array => array_diff_key($row->toArray(), ['id' => true]))
            ->all());
    }

    private function sessionsSummarisedOn(CarbonImmutable $day): int
    {
        return (int) DailySessionTotal::query()
            ->where('day', $day->toDateString())
            ->where('dimension', DailySessionTotal::DIMENSION_ALL)
            ->sum('sessions');
    }
}
