<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Actions\ArchiveClosedDaysAction;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\DailyArchive;
use Falcon\Analytics\Models\DailyCount;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Repositories\Dashboard\OverviewReadRepository;
use Falcon\Analytics\Services\DailyCountArchiver;
use Falcon\Analytics\Services\Dashboard\MarketingReportBuilder;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The test the whole retention rests on.
 *
 * Two blocks of the overview — the most seen pages and the most clicked
 * elements — read a closed day from its summary, and the day under way from its
 * rows. If the summary and the raw reading disagreed, the two blocks would jump
 * the night a day is summarised, and nothing would say why.
 *
 * So the raw reading is taken first, then the day is summarised, and the two
 * are compared · read afterwards, the screen would answer from the summary and
 * the comparison would hold against itself.
 */
final class TheSummaryAgreesWithTheDetailTest extends TestCase
{
    use RefreshDatabase;

    private OverviewReadRepository $overview;

    private DailyCountArchiver $archiver;

    private ArchiveClosedDaysAction $archiveClosedDays;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
        $this->overview = new OverviewReadRepository(new MarketingReportBuilder);
        $this->archiver = new DailyCountArchiver;
        $this->archiveClosedDays = new ArchiveClosedDaysAction($this->archiver);
    }

    private function newSession(bool $isBot = false, ?string $subjectType = null): Session
    {
        return Session::factory()->create([
            'is_bot' => $isBot,
            'subject_type' => $subjectType,
            'subject_id' => $subjectType !== null ? 1 : null,
        ]);
    }

    private function pageview(Session $session, string $url, CarbonImmutable $at): void
    {
        Event::factory()->for($session)->create(['url' => $url, 'occurred_at' => $at]);
    }

    private function click(Session $session, ?string $text, ?string $name, ?string $route, CarbonImmutable $at): void
    {
        Event::factory()->for($session)->click($name, $text)->create(['route' => $route, 'occurred_at' => $at]);
    }

    /**
     * A day with everything the two readings have to agree about · several
     * addresses, repeats, a named click without a visible text, a click with
     * neither text nor name, which counts nowhere, a bot, and two subjects.
     */
    private function aBusyDay(CarbonImmutable $day): void
    {
        $anonymous = $this->newSession();
        $client = $this->newSession(subjectType: 'client');
        $robot = $this->newSession(isBot: true);

        $this->pageview($anonymous, 'https://exemple.test/', $day->setTime(9, 0));
        $this->pageview($anonymous, 'https://exemple.test/', $day->setTime(10, 0));
        $this->pageview($anonymous, 'https://exemple.test/tarifs', $day->setTime(11, 0));
        $this->pageview($client, 'https://exemple.test/tarifs', $day->setTime(12, 0));

        // Bot rows are excluded from both readings, the easiest thing for a summary to forget.
        $this->pageview($robot, 'https://exemple.test/', $day->setTime(13, 0));

        $this->click($anonymous, 'Demander un devis', 'devis.demande', 'accueil', $day->setTime(9, 30));
        $this->click($anonymous, 'Demander un devis', 'devis.demande', 'accueil', $day->setTime(9, 40));

        $this->click($client, null, 'panier.ajout', 'tarifs', $day->setTime(12, 30));

        $this->click($anonymous, '', '', null, $day->setTime(9, 45));

        $this->click($robot, 'Demander un devis', 'devis.demande', 'accueil', $day->setTime(13, 30));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sorted(array $rows): array
    {
        $rows = array_values($rows);

        usort($rows, fn (array $a, array $b): int => [$b['total'], $a['label']] <=> [$a['total'], $b['label']]);

        return $rows;
    }

    /**
     * What the summary holds for a day, in the shape the raw reading answers with.
     *
     * @return list<array<string, mixed>>
     */
    private function summarised(CarbonImmutable $day, string $kind, bool $withRoute, ?string $subjectType = null): array
    {
        $grouped = [];

        $rows = DailyCount::query()
            ->where('day', $day->toDateString())
            ->where('kind', $kind)
            ->when($subjectType !== null, fn ($query) => $query->where('subject_type', $subjectType))
            ->get(['label', 'route', 'total']);

        foreach ($rows as $row) {
            $label = $row->label;
            $route = $row->route;
            $key = $label.'|'.($route ?? '');

            $grouped[$key] ??= $withRoute
                ? ['label' => $label, 'route' => $route, 'total' => 0]
                : ['label' => $label, 'total' => 0];

            $grouped[$key]['total'] += $row->total;
        }

        return $this->sorted(array_values($grouped));
    }

    public function test_the_summary_counts_the_same_pages_as_the_detail(): void
    {
        $day = CarbonImmutable::parse('2026-06-10');
        $this->aBusyDay($day);

        $oneDay = new Period($day->startOfDay(), $day->endOfDay(), 1);

        $fromDetail = $this->sorted(array_map(
            fn (array $row): array => ['label' => $row['label'], 'total' => $row['total']],
            $this->overview->topPages($oneDay, null, 20),
        ));

        $this->archiver->archive($day);
        $fromSummary = $this->summarised($day, DailyCount::KIND_PAGE, withRoute: false);

        $this->assertSame($fromDetail, $fromSummary);
        $this->assertNotSame([], $fromDetail, 'The day has to hold something, or this proves nothing.');
    }

    public function test_the_summary_counts_the_same_clicks_as_the_detail(): void
    {
        $day = CarbonImmutable::parse('2026-06-10');
        $this->aBusyDay($day);

        $oneDay = new Period($day->startOfDay(), $day->endOfDay(), 1);

        $fromDetail = $this->sorted(array_map(
            fn (array $row): array => ['label' => $row['label'], 'route' => $row['route'], 'total' => $row['total']],
            $this->overview->topClicks($oneDay, null, 20),
        ));

        $this->archiver->archive($day);
        $fromSummary = $this->summarised($day, DailyCount::KIND_CLICK, withRoute: true);

        $this->assertSame($fromDetail, $fromSummary);
        $this->assertNotSame([], $fromDetail);
    }

    /**
     * The screens filter on the subject of the session, so a summary that read
     * it off the event would drift once a visitor signs in mid-session.
     */
    public function test_the_summary_agrees_subject_by_subject(): void
    {
        $day = CarbonImmutable::parse('2026-06-10');
        $this->aBusyDay($day);

        $oneDay = new Period($day->startOfDay(), $day->endOfDay(), 1);

        $fromDetail = $this->sorted(array_map(
            fn (array $row): array => ['label' => $row['label'], 'total' => $row['total']],
            $this->overview->topPages($oneDay, 'client', 20),
        ));

        $this->archiver->archive($day);
        $fromSummary = $this->summarised($day, DailyCount::KIND_PAGE, withRoute: false, subjectType: 'client');

        $this->assertSame($fromDetail, $fromSummary);
        $this->assertNotSame([], $fromDetail);
    }

    /**
     * A day once summarised is read from its summary, whatever its rows still
     * hold · the reading of a long period then costs a day of rows, not ninety.
     *
     * A row erased after the night — a visitor erased on request — leaves the
     * two blocks as the night counted them.
     */
    public function test_a_summarised_day_is_read_from_its_summary(): void
    {
        $day = CarbonImmutable::parse('2026-06-10');
        $this->aBusyDay($day);
        $this->archiver->archive($day);

        $oneDay = new Period($day->startOfDay(), $day->endOfDay(), 1);
        $summarised = $this->overview->topPages($oneDay, null, 20);

        Event::query()->where('type', EventType::Pageview->value)->where('occurred_at', '>=', $day)->firstOrFail()->delete();

        $this->assertSame($summarised, $this->overview->topPages($oneDay, null, 20), 'The rows answered for a day its summary holds.');
    }

    /** No summary holds the day under way yet. */
    public function test_the_day_under_way_is_read_from_its_rows(): void
    {
        $yesterday = CarbonImmutable::now()->subDay()->startOfDay();
        $this->aBusyDay($yesterday);
        $this->archiver->archive($yesterday);

        $twoDays = new Period($yesterday, CarbonImmutable::now(), 2);
        $before = collect($this->overview->topPages($twoDays, null, 20))->sum('total');

        $this->pageview($this->newSession(), 'https://exemple.test/contact', CarbonImmutable::now()->subMinute());

        $this->assertSame($before + 1, collect($this->overview->topPages($twoDays, null, 20))->sum('total'));
    }

    /**
     * A button written across lines of HTML gives a label with line feeds, which
     * `textContent` keeps · the summary's signature and the joining of the two
     * halves must not cut the label at one.
     */
    public function test_a_label_that_spans_lines_comes_back_whole(): void
    {
        $day = CarbonImmutable::parse('2026-06-10');
        $session = $this->newSession();

        $overTwoLines = "Demander\nun devis";

        $this->click($session, $overTwoLines, null, 'accueil', $day->setTime(9, 0));
        $this->click($session, $overTwoLines, null, 'accueil', $day->setTime(9, 1));

        // Another button, labelled with the first line alone, so it stays its own row.
        $this->click($session, 'Demander', null, 'accueil', $day->setTime(9, 2));

        $this->archiver->archive($day);

        $oneDay = new Period($day->startOfDay(), $day->endOfDay(), 1);
        $read = $this->overview->topClicks($oneDay, null, 20);

        $byLabel = [];
        foreach ($read as $row) {
            $byLabel[$row['label']] = $row;
        }

        $this->assertArrayHasKey($overTwoLines, $byLabel, 'The label came back cut at its line feed.');
        $this->assertSame('accueil', $byLabel[$overTwoLines]['route'], 'And its page went with the cut.');
        $this->assertSame(2, $byLabel[$overTwoLines]['total']);

        $this->assertArrayHasKey('Demander', $byLabel);
        $this->assertSame('accueil', $byLabel['Demander']['route']);
        $this->assertSame(1, $byLabel['Demander']['total']);
    }

    /**
     * The scheduler, a screen load and a hand-run command can all land on the
     * same day · adding instead of rewriting would inflate figures silently.
     */
    public function test_summarising_a_day_twice_changes_nothing(): void
    {
        $day = CarbonImmutable::parse('2026-06-10');
        $this->aBusyDay($day);

        $this->archiver->archive($day);
        $once = DailyCount::query()->where('day', $day->toDateString())->sum('total');

        $this->archiver->archive($day);
        $twice = DailyCount::query()->where('day', $day->toDateString())->sum('total');

        $this->assertSame($once, $twice);
        $this->assertSame(1, DailyArchive::query()->where('day', $day->toDateString())->count());
    }

    /**
     * Deducing "archived" from the presence of counts would leave a quiet day
     * looking untreated forever, and the purge — which refuses untreated days —
     * would never move past it.
     */
    public function test_a_day_without_traffic_is_still_recorded(): void
    {
        $quiet = CarbonImmutable::parse('2026-06-10');

        $this->archiver->archive($quiet);

        $this->assertSame(0, DailyCount::query()->where('day', $quiet->toDateString())->count());
        $this->assertTrue($this->archiver->isArchived($quiet));
    }

    /**
     * The sequence goes forward from the last treated day, so a gap cannot
     * open, and it stops at yesterday · today is not closed.
     */
    public function test_it_takes_the_closed_days_in_order_and_leaves_today_alone(): void
    {
        $this->pageview($this->newSession(), 'https://exemple.test/', CarbonImmutable::parse('2026-06-12 10:00'));

        $done = $this->archiveClosedDays->execute();

        $this->assertSame(['2026-06-12', '2026-06-13', '2026-06-14'], $done);
        $this->assertFalse($this->archiver->isArchived(CarbonImmutable::parse('2026-06-15')), 'Today is not closed.');
    }

    public function test_a_bounded_run_resumes_where_it_stopped(): void
    {
        $this->pageview($this->newSession(), 'https://exemple.test/', CarbonImmutable::parse('2026-06-12 10:00'));

        $this->assertSame(['2026-06-12'], $this->archiveClosedDays->execute(1));
        $this->assertSame(['2026-06-13'], $this->archiveClosedDays->execute(1));
        $this->assertSame(['2026-06-14'], $this->archiveClosedDays->execute(1));
        $this->assertSame([], $this->archiveClosedDays->execute(1));
    }

    /**
     * Rows written into days already summarised · the forward sequence never
     * goes back to them, so they are summarised again from where they start.
     */
    public function test_days_written_behind_the_line_are_summarised_again(): void
    {
        $this->pageview($this->newSession(), 'https://exemple.test/', CarbonImmutable::parse('2026-06-14 10:00'));
        $this->archiveClosedDays->execute();

        $this->pageview($this->newSession(), 'https://exemple.test/tarifs', CarbonImmutable::parse('2026-06-11 10:00'));
        $this->pageview($this->newSession(), 'https://exemple.test/tarifs', CarbonImmutable::parse('2026-06-14 11:00'));

        $done = $this->archiveClosedDays->executeFrom(CarbonImmutable::parse('2026-06-11'));

        $this->assertSame(['2026-06-11', '2026-06-12', '2026-06-13', '2026-06-14'], $done);
        $this->assertSame(1, (int) DailyCount::query()->where('day', '2026-06-11')->sum('total'));
        $this->assertSame(2, (int) DailyCount::query()->where('day', '2026-06-14')->sum('total'));
    }

    public function test_summarising_again_leaves_no_gap_behind_a_stalled_sequence(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-11 12:00:00'));
        $this->pageview($this->newSession(), 'https://exemple.test/', CarbonImmutable::parse('2026-06-10 10:00'));
        $this->archiveClosedDays->execute();

        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
        $this->pageview($this->newSession(), 'https://exemple.test/', CarbonImmutable::parse('2026-06-13 10:00'));

        $this->assertSame(
            ['2026-06-11', '2026-06-12', '2026-06-13', '2026-06-14'],
            $this->archiveClosedDays->executeFrom(CarbonImmutable::parse('2026-06-13')),
        );
    }

    /**
     * Past the retention the detail may be erased already · a day summarised
     * again there would count less than the summary it replaced.
     */
    public function test_summarising_again_never_reaches_past_the_retention(): void
    {
        config(['analytics.retention_days' => 3]);

        $this->pageview($this->newSession(), 'https://exemple.test/', CarbonImmutable::parse('2026-06-08 10:00'));
        $this->archiveClosedDays->execute();
        Event::query()->whereDate('occurred_at', '2026-06-08')->delete();

        $done = $this->archiveClosedDays->executeFrom(CarbonImmutable::parse('2026-06-08'));

        $this->assertSame(['2026-06-12', '2026-06-13', '2026-06-14'], $done);
        $this->assertSame(1, (int) DailyCount::query()->where('day', '2026-06-08')->sum('total'), 'The erased day kept its summary.');
    }

    /**
     * Each day is summarised in its own transaction · a day that fails is
     * undone whole, and the days before it stay summarised, so the next run
     * resumes at the one that failed instead of starting over.
     */
    public function test_a_day_that_fails_leaves_the_days_before_it_summarised(): void
    {
        $this->pageview($this->newSession(), 'https://exemple.test/', CarbonImmutable::parse('2026-06-12 10:00'));
        $this->pageview($this->newSession(), 'https://exemple.test/', CarbonImmutable::parse('2026-06-13 10:00'));

        DB::listen(function (QueryExecuted $query): void {
            if (str_starts_with($query->sql, 'insert into `falcon_analytics_daily_archives`') && in_array('2026-06-13', $query->bindings, true)) {
                throw new RuntimeException('The 13th fails here.');
            }
        });

        try {
            $this->archiveClosedDays->execute();
            $this->fail('The 13th was meant to fail.');
        } catch (RuntimeException $failure) {
            $this->assertSame('The 13th fails here.', $failure->getMessage());
        }

        $this->assertTrue($this->archiver->isArchived(CarbonImmutable::parse('2026-06-12')));
        $this->assertFalse($this->archiver->isArchived(CarbonImmutable::parse('2026-06-13')));
        $this->assertSame(0, DailyCount::query()->where('day', '2026-06-13')->count(), 'The failed day kept half its summary.');
        $this->assertSame(1, DailyCount::query()->where('day', '2026-06-12')->count());
    }

    /**
     * The collector sends an event seconds after it happens, so a row can land
     * after its day has ended, and a screen load can run the catch-up at
     * midnight. Yesterday closes only after a grace no collector delay comes near.
     */
    public function test_just_after_midnight_yesterday_is_still_open(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 00:00:30'));
        $this->pageview($this->newSession(), 'https://exemple.test/', CarbonImmutable::parse('2026-06-13 10:00'));

        $this->assertSame(['2026-06-13'], $this->archiveClosedDays->execute(), 'Only the day before yesterday is closed at 00:00:30.');
        $this->assertFalse($this->archiver->isArchived(CarbonImmutable::parse('2026-06-14')), 'Yesterday is still open.');
    }

    public function test_after_the_grace_yesterday_is_closed(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 01:00:00'));
        $this->pageview($this->newSession(), 'https://exemple.test/', CarbonImmutable::parse('2026-06-13 10:00'));

        $this->assertSame(['2026-06-13', '2026-06-14'], $this->archiveClosedDays->execute());
    }

    /**
     * A screen load at 00:00:01 runs the catch-up, then the row of a page opened
     * at 23:59:58 lands · the nightly run at 03:00 summarises it into its own day.
     */
    public function test_a_row_that_lands_after_midnight_is_still_counted(): void
    {
        $day = CarbonImmutable::parse('2026-06-14');
        $session = $this->newSession();

        // Without a visit the day has nothing to summarise at midnight, and the grace would go untested.
        $this->pageview($session, 'https://exemple.test/', $day->setTime(10, 0));

        $this->travelTo(CarbonImmutable::parse('2026-06-15 00:00:01'));
        $this->archiveClosedDays->execute();

        $this->pageview($session, 'https://exemple.test/tarifs', $day->setTime(23, 59, 58));

        $this->travelTo(CarbonImmutable::parse('2026-06-15 03:00:00'));
        $this->archiveClosedDays->execute();

        $counted = DailyCount::query()
            ->where('day', '2026-06-14')
            ->where('label', '/tarifs')
            ->value('total');

        $this->assertSame(1, (int) $counted, 'The late row was summarised into its own day.');
    }
}
