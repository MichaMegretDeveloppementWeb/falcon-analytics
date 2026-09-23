<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelEvaluator;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\AdObjective;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Repositories\Dashboard\EventReadRepository;
use Falcon\Analytics\Repositories\Dashboard\OverviewReadRepository;
use Falcon\Analytics\Services\Dashboard\MarketingReportBuilder;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The promise the whole design exists for · erasing changes no figure.
 *
 * Everything the purge could falsify is read, erased, and read again, block by
 * block, so a failure names the block that moved. The one thing that does
 * change, a session's step-by-step, has its own test.
 */
final class ThePurgeChangesNoFigureTest extends TestCase
{
    use RefreshDatabase;

    private const ANCIENT = '2026-03-01';

    private OverviewReadRepository $overview;

    private EventReadRepository $events;

    private Period $wideEnoughToReachBack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

        config([
            'analytics.retention_days' => 30,
            'analytics.funnels_path' => __DIR__.'/../Fixtures/analytics-funnels.php',
            'analytics.events_path' => __DIR__.'/../Fixtures/analytics-events.php',
        ]);

        $this->app->forgetInstance(FunnelRegistry::class);
        $this->app->forgetInstance(EventRegistry::class);

        $this->overview = new OverviewReadRepository(new MarketingReportBuilder);
        $this->events = new EventReadRepository;

        // Both halves: days the purge empties and days it leaves, so the join is exercised.
        $this->wideEnoughToReachBack = new Period(
            CarbonImmutable::parse('2026-02-01')->startOfDay(),
            CarbonImmutable::now(),
            135,
        );
    }

    /** A new visitor's visit, arrived through the campaign link. */
    private function newSession(CarbonImmutable $at, ?string $subjectType = null): Session
    {
        return Session::factory()->at($at)->create([
            'subject_type' => $subjectType,
            'subject_id' => $subjectType !== null ? 1 : null,
            'mkt_params' => ['src' => 'meta'],
        ]);
    }

    /** @param  array<string, mixed>  $extra */
    private function event(Session $session, EventType $type, CarbonImmutable $at, array $extra = []): void
    {
        Event::factory()->for($session)->create(['type' => $type, 'occurred_at' => $at, ...$extra]);
    }

    /**
     * A stretch of history on both sides of the line · pages, clicks, named
     * events, a funnel walked through, and a campaign with an objective.
     */
    private function aHistory(): void
    {
        $old = CarbonImmutable::parse(self::ANCIENT);
        $recent = CarbonImmutable::now()->subDays(3);

        $campaign = Campaign::factory()->matching('src', 'meta')->create(['name' => 'Été']);
        $ad = Ad::factory()->for($campaign)->matching('src', 'meta')->create(['name' => 'Cabrio']);
        AdObjective::factory()->for($ad)->event('sample.action')->create();

        foreach ([$old, $recent] as $when) {
            $session = $this->newSession($when);

            $this->event($session, EventType::Pageview, $when->setTime(9, 0), ['url' => 'https://exemple.test/', 'route' => 'home']);
            $this->event($session, EventType::Pageview, $when->setTime(9, 5), ['url' => 'https://exemple.test/tarifs', 'route' => 'tarifs']);

            // Two campaign links to one page: rows and summaries both group on `page`, the path alone.
            $this->event($session, EventType::Pageview, $when->setTime(9, 6), ['url' => 'https://exemple.test/tarifs?fbclid=IwAR0aaa', 'route' => 'tarifs']);
            $this->event($session, EventType::Pageview, $when->setTime(9, 7), ['url' => 'https://exemple.test/tarifs?utm_source=meta&utm_campaign=ete', 'route' => 'tarifs']);

            $this->event($session, EventType::Click, $when->setTime(9, 10), ['target_text' => 'Demander un devis', 'route' => 'home']);
            $this->event($session, EventType::Click, $when->setTime(9, 11), ['target_text' => 'Demander un devis', 'route' => 'home']);

            // The counters `IngestEventsAction` keeps, since these rows skip the endpoint.
            $session->update(['pageview_count' => 4, 'click_count' => 2, 'event_count' => 7]);

            // Read by the funnel's second step, the events screen and the ad's objective.
            $this->event($session, EventType::Custom, $when->setTime(9, 20), ['name' => 'sample.action', 'value' => 5]);

            $signed = $this->newSession($when, 'client');
            $this->event($signed, EventType::Pageview, $when->setTime(10, 0), ['url' => 'https://exemple.test/tarifs', 'route' => 'tarifs']);
        }

        // The previous window's last day, past the current hour: counted only if that window takes whole days.
        $lastDayOfThePreviousWindow = $this->wideEnoughToReachBack->previous()->to;
        $afternoon = $this->newSession($lastDayOfThePreviousWindow->setTime(15, 0));
        $this->event($afternoon, EventType::Pageview, $lastDayOfThePreviousWindow->setTime(15, 0), ['url' => 'https://exemple.test/tarifs', 'route' => 'tarifs']);
        $afternoon->update(['pageview_count' => 1, 'event_count' => 1]);
    }

    /** Summarise every closed day, then erase what the retention allows. */
    private function archiveThenPrune(): void
    {
        $this->artisan('analytics:archive')->assertSuccessful();
        $this->artisan('analytics:prune')->assertSuccessful();
    }

    /**
     * Everything the purge could falsify, in one reading.
     *
     * @return array<string, mixed>
     */
    private function everythingAtStake(): array
    {
        $period = $this->wideEnoughToReachBack;
        $registry = app(EventRegistry::class);

        return [
            'pages' => $this->overview->topPages($period, null, 20),
            'pages, pour un client' => $this->overview->topPages($period, 'client', 20),
            'clics' => $this->overview->topClicks($period, null, 20),
            'événements' => $this->events->eventBreakdown($period, null, $registry),
            'en-tête des événements' => $this->events->headline($period, null, $registry),
            'tunnels' => array_map(
                fn (object $report): array => (array) $report,
                app(FunnelEvaluator::class)->evaluateAll($period, null),
            ),
            'conversions marketing' => (new MarketingReportBuilder)->conversions($period, null, app(FunnelRegistry::class)),
        ];
    }

    public function test_erasing_changes_no_figure_of_any_screen(): void
    {
        $this->aHistory();

        $before = $this->everythingAtStake();

        $this->archiveThenPrune();

        // The erasing has to have happened, or this test would pass by doing nothing.
        $this->assertSame(
            0,
            Event::query()
                ->where('occurred_at', '<', CarbonImmutable::now()->subDays(30)->startOfDay())
                ->whereIn('type', [EventType::Pageview->value, EventType::Click->value])
                ->where(fn ($query) => $query->whereNull('name')->orWhere('name', ''))
                ->whereNotIn('route', ['home'])
                ->count(),
            'Nothing was erased, so nothing is being proved.',
        );

        $after = $this->everythingAtStake();

        foreach ($before as $block => $figures) {
            $this->assertEquals($figures, $after[$block], "Le bloc « {$block} » a bougé après l'effacement.");
        }
    }

    /**
     * A session's step-by-step is what the retention is spent on · what stays is
     * what the session counted and its named events, which are never erased.
     */
    public function test_an_old_session_keeps_its_counts_and_its_named_events(): void
    {
        $this->aHistory();
        $this->archiveThenPrune();

        $old = Session::query()
            ->where('started_at', CarbonImmutable::parse(self::ANCIENT))
            ->whereNull('subject_type')
            ->firstOrFail();

        $this->assertSame(4, $old->pageview_count, 'What it counted while it happened.');
        $this->assertSame(2, $old->click_count);

        $kept = Event::query()->where('session_id', $old->id)->get();

        $this->assertSame(
            ['sample.action'],
            $kept->pluck('name')->filter()->values()->all(),
            'The named event stays; the anonymous views and clicks are gone.',
        );
    }

    /**
     * The erasing deletes in batches, so an interruption leaves a day part
     * erased. Such a day reads right only if its summary answers, where a day
     * with all its rows reads right either way.
     */
    public function test_a_day_erased_halfway_reads_exactly(): void
    {
        $this->aHistory();
        $this->artisan('analytics:archive')->assertSuccessful();

        $before = $this->everythingAtStake();

        $cutoff = CarbonImmutable::now()->subDays(30)->startOfDay();

        // An interrupted erasing, by hand: one anonymous row it would take, on a route no funnel step protects.
        $halfDone = Event::query()
            ->where('occurred_at', '<', $cutoff)
            ->where('type', EventType::Pageview->value)
            ->whereNull('name')
            ->where('route', 'tarifs')
            ->firstOrFail();

        $halfDone->delete();

        $this->assertGreaterThan(
            0,
            Event::query()->where('occurred_at', '<', $cutoff)->whereNull('name')->count(),
            'Il doit rester des lignes, sinon ce n’est pas un effacement interrompu.',
        );

        $after = $this->everythingAtStake();

        foreach ($before as $block => $figures) {
            $this->assertEquals($figures, $after[$block], "Le bloc « {$block} » a bougé sur un effacement interrompu.");
        }
    }
}
