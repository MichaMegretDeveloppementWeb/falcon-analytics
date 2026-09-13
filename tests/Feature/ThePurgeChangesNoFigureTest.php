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
            $this->event($session, EventType::Click, $when->setTime(9, 10), ['target_text' => 'Demander un devis', 'route' => 'home']);
            $this->event($session, EventType::Click, $when->setTime(9, 11), ['target_text' => 'Demander un devis', 'route' => 'home']);

            // What the ingestion keeps as it happens, and what the session will
            // still be able to say once its rows are gone. Written here because
            // these rows are laid down directly rather than through the
            // endpoint; `IngestEventsAction` is what does it in the real path.
            $session->update(['pageview_count' => 2, 'click_count' => 2, 'event_count' => 5]);

            // Named: the funnel's second step, the events screen, and the ad's
            // objective all read this one.
            $this->event($session, EventType::Custom, $when->setTime(9, 20), ['name' => 'sample.action', 'value' => 5.0]);

            $signed = $this->newSession($this->visitor(), $when, 'client');
            $this->event($signed, EventType::Pageview, $when->setTime(10, 0), ['url' => 'https://exemple.fr/tarifs', 'route' => 'tarifs']);
        }
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

        $old = Session::query()->orderBy('started_at')->firstOrFail();

        $this->assertSame(2, $old->pageview_count, 'What it counted while it happened.');
        $this->assertSame(2, $old->click_count);

        $kept = Event::query()->where('session_id', $old->id)->get();

        $this->assertSame(
            ['sample.action'],
            $kept->pluck('name')->filter()->values()->all(),
            'The named event stays; the anonymous views and clicks are gone.',
        );
    }
}
