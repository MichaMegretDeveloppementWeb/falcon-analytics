<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Livewire\Admin\Widgets\EventsContent;
use Falcon\Analytics\Livewire\Admin\Widgets\OverviewAudience;
use Falcon\Analytics\Livewire\Admin\Widgets\OverviewHeadline;
use Falcon\Analytics\Livewire\Admin\Widgets\VisitorsHeadline;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Models\DailyArchive;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Repositories\Dashboard\EngagementReadRepository;
use Falcon\Analytics\Repositories\Dashboard\EventReadRepository;
use Falcon\Analytics\Repositories\Dashboard\OverviewReadRepository;
use Falcon\Analytics\Repositories\Dashboard\VisitorListReadRepository;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/**
 * Sessions and named events erased past their retention · every figure that
 * adds up reads the same from their daily totals, and every figure that counts
 * distinct people over those days says it is unavailable.
 *
 * The erasing here is done by hand, as the purge does it · the register marked
 * first, then the rows deleted.
 */
final class ErasedSessionsChangeNoFigureThatAddsUpTest extends TestCase
{
    use RefreshDatabase;

    private const ANCIENT = '2026-03-01 09:00:00';

    private Period $reachingBack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
        config([
            'analytics.session_retention_days' => 60,
            'analytics.events_path' => __DIR__.'/../Fixtures/analytics-events.php',
        ]);
        $this->app->forgetInstance(EventRegistry::class);

        $this->reachingBack = new Period(CarbonImmutable::parse('2026-02-01')->startOfDay(), CarbonImmutable::now(), 135);
    }

    public function test_every_figure_that_adds_up_reads_the_same(): void
    {
        $this->aHistory();
        $this->artisan('analytics:archive')->assertSuccessful();
        $before = $this->everythingThatAddsUp();

        $this->eraseBefore(CarbonImmutable::now()->subDays(60)->startOfDay());

        $this->assertSame(0, Session::query()->where('started_at', '<', '2026-04-01')->count(), 'Nothing was erased, so nothing is being proved.');

        $after = $this->everythingThatAddsUp();

        foreach ($before as $block => $figures) {
            $this->assertEquals($figures, $after[$block], "Le bloc « {$block} » a bougé après l'effacement.");
        }
    }

    public function test_every_figure_that_counts_people_says_it_is_unavailable(): void
    {
        $this->aHistory();
        $this->artisan('analytics:archive')->assertSuccessful();
        $this->eraseBefore(CarbonImmutable::now()->subDays(60)->startOfDay());

        $this->assertNull($this->app->make(EngagementReadRepository::class)->headlineCounts($this->reachingBack, null)['visitors']);
        $this->assertNull($this->app->make(OverviewReadRepository::class)->newVsReturning($this->reachingBack, null));
        $this->assertNull($this->app->make(VisitorListReadRepository::class)->visitorCounts($this->reachingBack, null));

        foreach ($this->app->make(EventReadRepository::class)->eventBreakdown($this->reachingBack, null, $this->app->make(EventRegistry::class)) as $row) {
            $this->assertNull($row['visitors'], "Les visiteurs de « {$row['name']} » ont été comptés sur des jours effacés.");
        }
    }

    /** A period that stays within the rows kept counts its people as before. */
    public function test_a_period_within_the_rows_kept_counts_its_people(): void
    {
        $this->aHistory();
        $this->artisan('analytics:archive')->assertSuccessful();
        $this->eraseBefore(CarbonImmutable::now()->subDays(60)->startOfDay());

        $within = Period::ofDays(30);

        $this->assertSame(3, $this->app->make(EngagementReadRepository::class)->headlineCounts($within, null)['visitors']);
        $this->assertNotNull($this->app->make(VisitorListReadRepository::class)->visitorCounts($within, null));
    }

    /** The screens say it · the figure gives way to its sentence, the comparison to its own. */
    public function test_the_screens_say_what_is_unavailable(): void
    {
        config(['analytics.session_retention_days' => 100]);
        $this->aHistory();
        $this->artisan('analytics:archive')->assertSuccessful();
        $this->eraseBefore(CarbonImmutable::now()->subDays(100)->startOfDay());
        $this->actingAs(TestAdmin::create([]), 'admin');

        // Kept a hundred days, the 90-day view reaches the erased days through its previous period only.
        Livewire::test(OverviewHeadline::class, ['period' => 90])->call('$refresh')
            ->assertSee('Comparaison indisponible')
            ->assertDontSee('Indisponible au-delà');

        Livewire::test(VisitorsHeadline::class, ['period' => 90])->call('$refresh')
            ->assertSee('Comparaison indisponible');

        Livewire::test(OverviewAudience::class, ['period' => 90])->call('$refresh')
            ->assertSee('Comparaison indisponible');

        config(['analytics.session_retention_days' => 30, 'analytics.retention_days' => 30]);
        $this->eraseBefore(CarbonImmutable::now()->subDays(30)->startOfDay());

        // Kept thirty days, the 90-day view reaches them with its own days.
        Livewire::test(OverviewHeadline::class, ['period' => 90])->call('$refresh')
            ->assertSee('Indisponible au-delà de 30 jours de conservation');

        Livewire::test(VisitorsHeadline::class, ['period' => 90])->call('$refresh')
            ->assertSee('Indisponible au-delà de 30 jours de conservation');

        Livewire::test(OverviewAudience::class, ['period' => 90])->call('$refresh')
            ->assertSee('Indisponible au-delà de 30 jours de conservation');

        Livewire::test(EventsContent::class, ['period' => 90])->call('$refresh')
            ->assertSee('Indisponible');
    }

    /**
     * A stretch of history on both sides of the line · sessions from devices,
     * places and sources, one of them claimed by a campaign, named events with
     * and without a score, and a bounce.
     */
    private function aHistory(): void
    {
        $campaign = Campaign::factory()->matching('src', 'meta')->create(['name' => 'Été']);
        Ad::factory()->for($campaign)->matching('src', 'meta')->create(['name' => 'Cabrio']);

        foreach ([CarbonImmutable::parse(self::ANCIENT), CarbonImmutable::parse('2026-04-20 10:00:00'), CarbonImmutable::now()->subDays(3)] as $when) {
            $claimed = $this->sessionAt($when, ['device_type' => 'mobile', 'country' => 'FR', 'city' => 'Paris', 'source' => 'social', 'mkt_params' => ['src' => 'meta'], 'pageview_count' => 3], 300);
            Event::factory()->for($claimed)->custom('sample.action')->create(['occurred_at' => $when, 'value' => 7]);
            Event::factory()->for($claimed)->custom('sample.other')->create(['occurred_at' => $when]);

            $bounce = $this->sessionAt($when->addHour(), ['device_type' => 'desktop', 'country' => 'BE', 'city' => null, 'source' => 'google', 'pageview_count' => 1], 0);
            Event::factory()->for($bounce)->custom('sample.action')->create(['occurred_at' => $when->addHour()]);

            $this->sessionAt($when->addHours(2), ['device_type' => 'tablet', 'country' => 'FR', 'city' => 'Lyon', 'source' => 'direct', 'pageview_count' => 2, 'subject_type' => 'client', 'subject_id' => 1], 45);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function sessionAt(CarbonImmutable $at, array $attributes, int $seconds): Session
    {
        return Session::factory()->create([...$attributes, 'started_at' => $at, 'last_activity_at' => $at->addSeconds($seconds)]);
    }

    /** As the purge does it · the days marked, then their sessions deleted, their events with them. */
    private function eraseBefore(CarbonImmutable $cutoff): void
    {
        DailyArchive::query()->where('day', '<', $cutoff->toDateString())->update([
            'sessions_pruned_at' => CarbonImmutable::now(),
            'events_pruned_at' => CarbonImmutable::now(),
        ]);

        Session::query()->where('last_activity_at', '<', $cutoff)->delete();
        $this->app->forgetScopedInstances();
    }

    /**
     * @return array<string, mixed>
     */
    private function everythingThatAddsUp(): array
    {
        $this->app->forgetScopedInstances();
        $engagement = $this->app->make(EngagementReadRepository::class);
        $overview = $this->app->make(OverviewReadRepository::class);
        $events = $this->app->make(EventReadRepository::class);
        $registry = $this->app->make(EventRegistry::class);
        $counts = $engagement->headlineCounts($this->reachingBack, null);
        unset($counts['visitors']);

        $breakdown = array_map(function (array $row): array {
            unset($row['visitors']);

            return $row;
        }, $events->eventBreakdown($this->reachingBack, null, $registry));

        $sparkline = array_map(function (array $row): array {
            unset($row['visitors']);

            return $row;
        }, $engagement->sparklineRows($this->reachingBack, null));
        ksort($sparkline);

        return [
            'en-tête' => $counts,
            'en-tête, pour un client' => array_diff_key($engagement->headlineCounts($this->reachingBack, 'client'), ['visitors' => true]),
            'courbes' => $sparkline,
            'tendance' => $overview->trendRows($this->reachingBack, null),
            'appareils' => $overview->sessionsByDevice($this->reachingBack, null),
            'lieux' => $overview->topLocalities($this->reachingBack, null, 20),
            'sources' => $overview->topSources($this->reachingBack, null, 20),
            'événements' => $breakdown,
            'événements par jour' => $events->daily($this->reachingBack, null, $registry),
        ];
    }
}
