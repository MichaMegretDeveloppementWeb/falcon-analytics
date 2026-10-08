<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Events\TrackedEvent;
use Falcon\Analytics\Livewire\Admin\Widgets\AdDetailContent;
use Falcon\Analytics\Livewire\Admin\Widgets\CampaignDetailContent;
use Falcon\Analytics\Livewire\Admin\Widgets\MarketingDashboardContent;
use Falcon\Analytics\Livewire\Admin\Widgets\OverviewEvents;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\Dashboard\EventReadRepository;
use Falcon\Analytics\Repositories\Dashboard\SessionListReadRepository;
use Falcon\Analytics\Services\SubjectResolver;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/** A figure counts what its label names · nothing more, nothing less. */
final class TheFiguresCountWhatTheyNameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-07-10 12:00:00'));
        $this->actingAs(TestAdmin::create([]), 'admin');
    }

    /** An event sent with an empty name is not a named event. */
    public function test_an_empty_name_names_no_event(): void
    {
        $session = Session::factory()->at(CarbonImmutable::parse('2026-07-09 10:00:00'))->create();
        Event::factory()->for($session)->custom('devis.demande')->create(['occurred_at' => '2026-07-09 10:00:00']);
        Event::factory()->for($session)->custom('')->create(['occurred_at' => '2026-07-09 10:01:00']);

        $events = $this->app->make(EventReadRepository::class);
        $registry = $this->app->make(EventRegistry::class);

        $this->assertSame(['devis.demande'], array_column($events->eventBreakdown(Period::ofDays(30), null, $registry), 'name'));
        $this->assertSame(['2026-07-09' => 1], $events->daily(Period::ofDays(30), null, $registry)['events']);

        $listed = $this->app->make(SessionListReadRepository::class)
            ->paginateSessions(Period::ofDays(30), null, null, null, null, new SubjectResolver);

        $this->assertSame(1, (int) $listed->items()[0]->getAttribute('events_count'));
    }

    /** « Conversions sur la période » adds every conversion up, not the six it lists. */
    public function test_the_overview_adds_every_conversion_up(): void
    {
        $registry = $this->app->make(EventRegistry::class);
        $session = Session::factory()->at(CarbonImmutable::parse('2026-07-09 10:00:00'))->create();

        foreach (range(1, 7) as $rank) {
            $registry->register(TrackedEvent::define("objectif.{$rank}", "Objectif {$rank}", value: 1));
            Event::factory()->for($session)->custom("objectif.{$rank}")->count(8 - $rank)->create(['occurred_at' => '2026-07-09 10:00:00']);
        }

        Livewire::test(OverviewEvents::class, ['period' => 30])->call('$refresh')
            ->assertViewHas('topConversions', fn (array $listed): bool => count($listed) === 6)
            ->assertViewHas('conversionsTotal', 7 + 6 + 5 + 4 + 3 + 2 + 1);
    }

    /** The line under a visitors figure draws visitors, not sessions · on the marketing screen, a campaign and an ad. */
    public function test_the_visitors_line_draws_visitors(): void
    {
        $campaign = Campaign::factory()->matching('src', 'meta_ete')->create(['name' => 'Été']);
        $ad = Ad::factory()->for($campaign)->matching('src', 'meta_ete')->create(['name' => 'Été générique']);
        $loyal = Visitor::factory()->create();

        foreach (['2026-07-09 09:00:00', '2026-07-09 15:00:00', '2026-07-09 18:00:00'] as $at) {
            Session::factory()->for($loyal)->at(CarbonImmutable::parse($at))->create(['mkt_params' => ['src' => 'meta_ete']]);
        }

        $screens = [
            [MarketingDashboardContent::class, ['period' => 7]],
            [CampaignDetailContent::class, ['period' => 7, 'refId' => $campaign->id]],
            [AdDetailContent::class, ['period' => 7, 'refId' => $ad->id]],
        ];

        foreach ($screens as [$screen, $parameters]) {
            Livewire::test($screen, $parameters)->call('$refresh')
                ->assertViewHas('trendData', fn (array $sessions): bool => $sessions[5] === 3)
                ->assertViewHas('visitorsTrend', fn (array $visitors): bool => $visitors[5] === 1 && array_sum($visitors) === 1);
        }
    }
}
