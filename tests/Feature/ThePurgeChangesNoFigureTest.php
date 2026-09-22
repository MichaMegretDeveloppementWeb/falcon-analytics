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
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\Dashboard\EventReadRepository;
use Falcon\Analytics\Repositories\Dashboard\OverviewReadRepository;
use Falcon\Analytics\Services\Dashboard\MarketingReportBuilder;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * The promise the whole design exists for · erasing changes no figure.
 *
 * Before this, the purge made three areas lie. A campaign could read « 1 200
 * visiteurs, 0 conversion » having made forty, the events screen emptied from
 * its oldest edge, and the funnels reported nothing beyond the retention —
 * none of it visible, all of it wrong.
 *
 * So this measures everything that was at stake, erases, and measures again.
 * **The two readings have to be identical**, and the essay says so figure by
 * figure rather than through one comparison, so that a failure names the block
 * that moved.
 *
 * The one thing that does change has its own essay · a session's step-by-step,
 * which is what the retention is spent on.
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

        // Wide enough to hold both halves: days the purge will empty, and days
        // it will leave alone. A window on one side only would prove nothing
        // about the joining.
        $this->wideEnoughToReachBack = new Period(
            CarbonImmutable::parse('2026-02-01')->startOfDay(),
            CarbonImmutable::now(),
            135,
        );
    }

    private function visitor(): Visitor
    {
        return Visitor::create(['uuid' => (string) Str::uuid(), 'first_seen_at' => now(), 'last_seen_at' => now()]);
    }

    private function newSession(Visitor $visitor, CarbonImmutable $at, ?string $subjectType = null): Session
    {
        return Session::create([
            'visitor_id' => $visitor->id,
            'started_at' => $at,
            'last_activity_at' => $at,
            'is_bot' => false,
            'subject_type' => $subjectType,
            'subject_id' => $subjectType !== null ? 1 : null,
            'mkt_params' => ['src' => 'meta'],
        ]);
    }

    /** @param  array<string, mixed>  $extra */
    private function event(Session $session, EventType $type, CarbonImmutable $at, array $extra = []): void
    {
        Event::create([
            'session_id' => $session->id,
            'visitor_id' => $session->visitor_id,
            'type' => $type,
            'occurred_at' => $at,
            ...$extra,
        ]);
    }

    /**
     * A stretch of history on both sides of the line · pages, clicks, named
     * events, a funnel walked through, and a campaign with an objective.
     */
    private function aHistory(): void
    {
        $old = CarbonImmutable::parse(self::ANCIENT);
        $recent = CarbonImmutable::now()->subDays(3);

        $campaign = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta']]]);
        $ad = Ad::create(['campaign_id' => $campaign->id, 'name' => 'Cabrio', 'match_conditions' => [['param' => 'src', 'value' => 'meta']]]);
        AdObjective::create(['ad_id' => $ad->id, 'type' => 'event', 'reference' => 'sample.action']);

        foreach ([$old, $recent] as $when) {
            $visitor = $this->visitor();
            $session = $this->newSession($visitor, $when);

            $this->event($session, EventType::Pageview, $when->setTime(9, 0), ['url' => 'https://exemple.fr/', 'route' => 'home']);
            $this->event($session, EventType::Pageview, $when->setTime(9, 5), ['url' => 'https://exemple.fr/tarifs', 'route' => 'tarifs']);

            /*
             * The same page, reached through two campaign links whose token is
             * never twice the same.
             *
             * They are here so that this essay exercises the GROUPING across
             * the erasing, and not only the counting · the summary and the
             * reading it stands in for both group on the address up to the `?`,
             * and if either one ever stopped, « les pages les plus vues » would
             * change shape on the day the erasing crossed this history —
             * silently, since both readings would look plausible on their own.
             */
            $this->event($session, EventType::Pageview, $when->setTime(9, 6), ['url' => 'https://exemple.fr/tarifs?fbclid=IwAR0aaa', 'route' => 'tarifs']);
            $this->event($session, EventType::Pageview, $when->setTime(9, 7), ['url' => 'https://exemple.fr/tarifs?utm_source=meta&utm_campaign=ete', 'route' => 'tarifs']);

            $this->event($session, EventType::Click, $when->setTime(9, 10), ['target_text' => 'Demander un devis', 'route' => 'home']);
            $this->event($session, EventType::Click, $when->setTime(9, 11), ['target_text' => 'Demander un devis', 'route' => 'home']);

            // What the ingestion keeps as it happens, and what the session will
            // still be able to say once its rows are gone. Written here because
            // these rows are laid down directly rather than through the
            // endpoint; `IngestEventsAction` is what does it in the real path.
            $session->update(['pageview_count' => 4, 'click_count' => 2, 'event_count' => 7]);

            // Named: the funnel's second step, the events screen, and the ad's
            // objective all read this one.
            $this->event($session, EventType::Custom, $when->setTime(9, 20), ['name' => 'sample.action', 'value' => 5]);

            $signed = $this->newSession($this->visitor(), $when, 'client');
            $this->event($signed, EventType::Pageview, $when->setTime(10, 0), ['url' => 'https://exemple.fr/tarifs', 'route' => 'tarifs']);
        }

        /*
         * One visit on the LAST day of the previous window, in the afternoon.
         *
         * The « previous » figure every block carries is read over a window
         * that ends at the same hour as the current one, thirty days earlier.
         * Read from rows, that window stops at noon ; read from summaries, a
         * day is whole. So a visit on that day after noon is the one that tells
         * whether the erasing moves the previous figure — it did, until the
         * previous window was made of whole days.
         */
        $lastDayOfThePreviousWindow = $this->wideEnoughToReachBack->previous()->to;
        $afternoon = $this->newSession($this->visitor(), $lastDayOfThePreviousWindow->setTime(15, 0));
        $this->event($afternoon, EventType::Pageview, $lastDayOfThePreviousWindow->setTime(15, 0), ['url' => 'https://exemple.fr/tarifs', 'route' => 'tarifs']);
        $afternoon->update(['pageview_count' => 1, 'event_count' => 1]);
    }

    /** Summarise every closed day, then erase what the retention allows. */
    private function archiveThenPrune(): void
    {
        $this->artisan('analytics:archive')->assertSuccessful();
        $this->artisan('analytics:prune')->assertSuccessful();
    }

    /**
     * Everything the purge was able to falsify, in one reading.
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

        // The erasing has to have happened, or this essay would pass by doing
        // nothing at all — which is exactly how a guarantee stops guarding.
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
     * And what the retention is actually spent on · a session's step-by-step.
     *
     * It goes, and it is the only thing that does. What stays in its place is
     * what the session itself counted, plus the named events, which are never
     * erased.
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
     * An erasing that stops halfway costs no figure either.
     *
     * **The erasing deletes in batches, not in one statement**, so a timeout or
     * a killed process leaves a day part erased. Such a day is already read
     * from its summary, so the reading is exact whether its rows went or
     * stayed, and the leftovers go next time.
     *
     * This is the state an interruption leaves behind, and it is the one that
     * discriminates · a day whose rows are all still there reads right either
     * way, the two sources agreeing. A day that has lost half of them only
     * reads right if its summary answers.
     */
    public function test_a_day_erased_halfway_reads_exactly(): void
    {
        $this->aHistory();
        $this->artisan('analytics:archive')->assertSuccessful();

        $before = $this->everythingAtStake();

        $cutoff = CarbonImmutable::now()->subDays(30)->startOfDay();

        // The interrupted state, laid down by hand · the erasing stops after a
        // single row, one it would have taken · anonymous, and on a route no
        // funnel step protects.
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
