<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\AdObjective;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Services\Dashboard\MarketingReportBuilder;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class MarketingReportBuilderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, string>  $params
     */
    private function taggedSession(array $params, ?Visitor $visitor = null, ?CarbonImmutable $startedAt = null): Session
    {
        return Session::factory()
            ->at($startedAt ?? CarbonImmutable::now())
            ->create(['visitor_id' => $visitor ?? Visitor::factory(), 'mkt_params' => $params]);
    }

    public function test_it_resolves_the_most_specific_ad_whose_conditions_all_match_the_session_params(): void
    {
        $ete = Campaign::factory()->matching('src', 'meta_ete')->create(['name' => 'Été']);
        $generic = Ad::factory()->for($ete)->matching('src', 'meta_ete')->create(['name' => 'Été générique']);
        $cabrio = Ad::factory()->for($ete)->create(['name' => 'Cabriolet', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete'], ['param' => 'creative', 'value' => 'cabrio']]]);

        $builder = new MarketingReportBuilder;

        // Two conditions beat one.
        $twoConditions = $builder->resolveAd(['src' => 'meta_ete', 'creative' => 'cabrio']);
        $otherCreative = $builder->resolveAd(['src' => 'meta_ete', 'creative' => 'other']);
        $sourceOnly = $builder->resolveAd(['src' => 'meta_ete']);

        $this->assertNotNull($twoConditions);
        $this->assertNotNull($otherCreative);
        $this->assertNotNull($sourceOnly);

        $this->assertSame($cabrio->id, $twoConditions->id);
        $this->assertSame($generic->id, $otherCreative->id);
        $this->assertSame($generic->id, $sourceOnly->id);
    }

    public function test_it_returns_null_when_a_condition_is_unmet_and_resolves_the_campaign_independently(): void
    {
        $ete = Campaign::factory()->matching('src', 'meta_ete')->create(['name' => 'Été']);
        Ad::factory()->for($ete)->create(['name' => 'Cabrio', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete'], ['param' => 'creative', 'value' => 'cabrio']]]);

        $builder = new MarketingReportBuilder;

        $this->assertNull($builder->resolveAd(['src' => 'meta_hiver', 'creative' => 'cabrio']), 'src differs');
        $this->assertNull($builder->resolveAd(['src' => 'meta_ete']), 'creative manque');
        $this->assertNull($builder->resolveAd([]));
        $matched = $builder->resolveCampaign(['src' => 'meta_ete']);

        $this->assertNotNull($matched);
        $this->assertSame($ete->id, $matched->id);
        $this->assertNull($builder->resolveCampaign(['src' => 'meta_hiver']));
    }

    public function test_it_ignores_inactive_ads_and_conditionless_definitions(): void
    {
        $ete = Campaign::factory()->matching('src', 'meta_ete')->create(['name' => 'Été']);
        Ad::factory()->for($ete)->matching('src', 'meta_ete')->create(['name' => 'Off', 'is_active' => false]);
        Ad::factory()->for($ete)->create(['name' => 'Empty', 'match_conditions' => []]);

        $this->assertNull((new MarketingReportBuilder)->resolveAd(['src' => 'meta_ete']));
    }

    public function test_it_aggregates_ad_driven_traffic_per_campaign_and_ad_over_the_period(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $ete = Campaign::factory()->matching('src', 'meta_ete')->create(['name' => 'Été']);
        $cabrio = Ad::factory()->for($ete)->create(['name' => 'Cabrio', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete'], ['param' => 'creative', 'value' => 'cabrio']]]);

        $visitor = Visitor::factory()->create();
        $this->taggedSession(['src' => 'meta_ete', 'creative' => 'cabrio'], $visitor);
        $this->taggedSession(['src' => 'meta_ete', 'creative' => 'cabrio'], $visitor);
        $this->taggedSession(['src' => 'meta_ete', 'creative' => 'other']);
        $this->taggedSession(['src' => 'other']);

        $builder = new MarketingReportBuilder;
        $period = Period::ofDays(30);

        // Only sessions attached to a campaign count as paid traffic: the
        // src=other session is set aside.
        $this->assertSame(['sessions' => 3, 'visitors' => 2], $builder->headline($period, null));

        $performance = $builder->performance($period, null);

        $this->assertSame(['sessions' => 3, 'visitors' => 2], $performance['campaigns'][$ete->id]);
        $this->assertSame(['sessions' => 2, 'visitors' => 1], $performance['ads'][$cabrio->id]);
    }

    /**
     * One campaign and one ad, each read over the period · its sessions, its
     * distinct visitors, its sessions per day and, for a campaign, the share of
     * each of its ads, all under most-specific attribution.
     */
    public function test_a_campaign_and_an_ad_report_their_own_traffic(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $ete = Campaign::factory()->matching('src', 'meta_ete')->create(['name' => 'Été']);
        $hiver = Campaign::factory()->matching('src', 'meta_hiver')->create(['name' => 'Hiver']);
        $generic = Ad::factory()->for($ete)->matching('src', 'meta_ete')->create(['name' => 'Été générique']);
        $cabrio = Ad::factory()->for($ete)->create(['name' => 'Cabriolet', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete'], ['param' => 'creative', 'value' => 'cabrio']]]);
        $neige = Ad::factory()->for($hiver)->matching('src', 'meta_hiver')->create(['name' => 'Neige']);

        $loyal = Visitor::factory()->create();
        $matched = [
            $this->taggedSession(['src' => 'meta_ete', 'creative' => 'cabrio'], $loyal, CarbonImmutable::parse('2026-06-10 09:00')),
            $this->taggedSession(['src' => 'meta_ete', 'creative' => 'cabrio'], $loyal, CarbonImmutable::parse('2026-06-11 09:00')),
            $this->taggedSession(['src' => 'meta_ete', 'creative' => 'other'], null, CarbonImmutable::parse('2026-06-11 10:00')),
            $this->taggedSession(['src' => 'meta_ete'], null, CarbonImmutable::parse('2026-06-12 10:00')),
            $this->taggedSession(['src' => 'meta_hiver'], null, CarbonImmutable::parse('2026-06-12 11:00')),
        ];
        $this->taggedSession(['src' => 'other'], null, CarbonImmutable::parse('2026-06-12 12:00'));

        $builder = new MarketingReportBuilder;
        $period = Period::ofDays(30);

        // The whole of it first · every session a campaign claims, and no other.
        $this->assertSame(['sessions' => 5, 'visitors' => 4], $builder->headline($period, null));
        $daily = $builder->dailySessions($period, null);
        ksort($daily);
        $this->assertSame(['2026-06-10' => 1, '2026-06-11' => 2, '2026-06-12' => 2], $daily);
        $sources = $builder->matchedSessionSources($period, null);
        ksort($sources);
        $this->assertSame(array_fill_keys(array_map(fn (Session $session): int => $session->id, $matched), 'direct'), $sources);

        $this->assertSame([
            'sessions' => 4,
            'visitors' => 3,
            'daily' => ['2026-06-10' => 1, '2026-06-11' => 2, '2026-06-12' => 1],
            'ads' => [
                $generic->id => ['sessions' => 2, 'visitors' => 2],
                $cabrio->id => ['sessions' => 2, 'visitors' => 1],
            ],
        ], $this->sortedReport($builder->campaignReport($period, null, $ete)));

        $this->assertSame([
            'sessions' => 1,
            'visitors' => 1,
            'daily' => ['2026-06-12' => 1],
            'ads' => [$neige->id => ['sessions' => 1, 'visitors' => 1]],
        ], $this->sortedReport($builder->campaignReport($period, null, $hiver)));

        $this->assertSame(
            ['sessions' => 2, 'visitors' => 1, 'daily' => ['2026-06-10' => 1, '2026-06-11' => 1]],
            $this->sortedReport($builder->adReport($period, null, $cabrio)),
        );

        $this->assertSame(
            ['sessions' => 2, 'visitors' => 2, 'daily' => ['2026-06-11' => 1, '2026-06-12' => 1]],
            $this->sortedReport($builder->adReport($period, null, $generic)),
        );
    }

    /**
     * A report with its keyed lists in key order, so two readings compare.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function sortedReport(array $report): array
    {
        foreach (['daily', 'ads'] as $list) {
            if (is_array($report[$list] ?? null)) {
                ksort($report[$list]);
            }
        }

        return $report;
    }

    public function test_it_credits_an_ad_with_a_conversion_when_its_visitor_completes_an_event_objective(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $ete = Campaign::factory()->matching('src', 'meta_ete')->create(['name' => 'Été']);
        $ad = Ad::factory()->for($ete)->matching('src', 'meta_ete')->create(['name' => 'Cabrio']);
        AdObjective::factory()->for($ad)->event('Lead')->create();

        $converter = Visitor::factory()->create();
        $session = $this->taggedSession(['src' => 'meta_ete'], $converter);
        Event::factory()->for($session)->custom('Lead')->create();

        // A second visitor who came through the ad and never fired the event.
        $this->taggedSession(['src' => 'meta_ete']);

        $result = (new MarketingReportBuilder)->conversions(Period::ofDays(30), null, app(FunnelRegistry::class));

        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['ads'][$ad->id]);
        $this->assertSame(1, $result['campaigns'][$ete->id]);
        $this->assertSame(1, $result['objectives'][$ad->id]['Lead']);
    }

    /**
     * A visitor driven by two ads credits both, and this is not first-touch.
     *
     * **The documentation claimed first-touch until 2026-09-13**, which is the
     * opposite of what happens: the attribution phase collects the SET of ads a
     * visitor arrived through in the period, and the conversion is credited to
     * every one of them. So the per-ad conversions can add up to more than the
     * total, and that is coherent rather than a double count — the total counts
     * distinct converting visitors.
     *
     * Written the day the claim was corrected, so that whichever of the two
     * rules is wanted has to be chosen out loud rather than drifted into.
     */
    public function test_a_visitor_driven_by_two_ads_credits_both_and_counts_once_in_the_total(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $campaign = Campaign::factory()->matching('src', 'a')->create(['name' => 'Été']);

        $first = Ad::factory()->for($campaign)->matching('src', 'a')->create(['name' => 'Première']);
        $second = Ad::factory()->for($campaign)->matching('src', 'b')->create(['name' => 'Seconde']);

        foreach ([$first, $second] as $ad) {
            AdObjective::factory()->for($ad)->event('Lead')->create();
        }

        // One person, two arrivals, two different ads — then one conversion.
        $visitor = Visitor::factory()->create();
        $this->taggedSession(['src' => 'a'], $visitor);
        $session = $this->taggedSession(['src' => 'b'], $visitor);

        Event::factory()->for($session)->custom('Lead')->create();

        $result = (new MarketingReportBuilder)->conversions(Period::ofDays(30), null, app(FunnelRegistry::class));

        // Read through a default rather than by key: crediting only one ad is
        // exactly what a slide back to first-touch looks like, and an undefined
        // key would report it as a missing index instead of as a rule change.
        $this->assertSame(1, $result['ads'][$first->id] ?? 0, 'The first ad is credited.');
        $this->assertSame(1, $result['ads'][$second->id] ?? 0, 'And so is the second: attribution is not first-touch.');
        $this->assertSame(1, $result['total'], 'One person converted once, whatever the ads say.');
    }

    public function test_it_credits_a_funnel_objective_and_reports_per_step_reach(): void
    {
        config(['analytics.funnels_path' => __DIR__.'/../Fixtures/analytics-funnels.php']);
        $this->app->forgetInstance(FunnelRegistry::class);
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $ete = Campaign::factory()->matching('src', 'meta_ete')->create(['name' => 'Été']);
        $ad = Ad::factory()->for($ete)->matching('src', 'meta_ete')->create(['name' => 'Cabrio']);
        AdObjective::factory()->for($ad)->funnel('sample')->create();

        // A visitor attached to the ad, who walks the funnel in order:
        // pageview home, then sample.action.
        $converter = Visitor::factory()->create();
        $session = $this->taggedSession(['src' => 'meta_ete'], $converter);
        Event::factory()->for($session)->create(['route' => 'home', 'occurred_at' => now()->subMinutes(2)]);
        Event::factory()->for($session)->custom('sample.action')->create(['occurred_at' => now()->subMinute()]);

        // Another visitor who came through the ad and only reaches the first
        // step: no conversion.
        $halfway = Visitor::factory()->create();
        $halfSession = $this->taggedSession(['src' => 'meta_ete'], $halfway);
        Event::factory()->for($halfSession)->create(['route' => 'home', 'occurred_at' => now()->subMinutes(2)]);

        $funnels = app(FunnelRegistry::class);
        $result = (new MarketingReportBuilder)->conversions(Period::ofDays(30), null, $funnels);

        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['ads'][$ad->id]);
        $this->assertSame(1, $result['campaigns'][$ete->id]);

        $elements = (new MarketingReportBuilder)->conversionElements(
            Period::ofDays(30),
            null,
            $funnels,
            app(EventRegistry::class),
            array_values(Ad::query()->with('objectives')->get()->all()),
        );

        $this->assertCount(1, $elements);
        $this->assertSame('funnel', $elements[0]['type']);
        $this->assertSame('sample', $elements[0]['reference']);
        $this->assertSame(1, $elements[0]['conversions']);
        $this->assertSame([
            ['label' => 'Viewed', 'count' => 2],
            ['label' => 'Acted', 'count' => 1],
        ], $elements[0]['steps']);
    }

    public function test_it_batches_event_objective_conversions_into_one_query_and_buckets_them_per_ad(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $ete = Campaign::factory()->matching('src', 'meta_ete')->create(['name' => 'Été']);
        $ad1 = Ad::factory()->for($ete)->create(['name' => 'A1', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete'], ['param' => 'creative', 'value' => 'c1']]]);
        $ad2 = Ad::factory()->for($ete)->create(['name' => 'A2', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete'], ['param' => 'creative', 'value' => 'c2']]]);
        AdObjective::factory()->for($ad1)->event('Lead')->create();
        AdObjective::factory()->for($ad2)->event('Lead')->create();

        $v1 = Visitor::factory()->create();
        $s1 = $this->taggedSession(['src' => 'meta_ete', 'creative' => 'c1'], $v1);
        Event::factory()->for($s1)->custom('Lead')->create();

        $v2 = Visitor::factory()->create();
        $s2 = $this->taggedSession(['src' => 'meta_ete', 'creative' => 'c2'], $v2);
        Event::factory()->for($s2)->custom('Lead')->create();

        DB::enableQueryLog();

        $elements = (new MarketingReportBuilder)->conversionElements(
            Period::ofDays(30), null, app(FunnelRegistry::class), app(EventRegistry::class),
            array_values(Ad::query()->with('objectives')->get()->all()),
        );

        $eventQueries = Collection::make(DB::getQueryLog())
            ->filter(fn (array $q): bool => str_contains($q['query'], 'falcon_analytics_events'))
            ->count();

        DB::disableQueryLog();

        $byAd = Collection::make($elements)->keyBy('adId');

        // Each ad is credited only with its own visitor's conversion, from one
        // single grouped read.
        // Each ad has to have its element: without it, reading a column would
        // fail without saying which of the two is missing.
        $firstAd = $byAd->get($ad1->id);
        $secondAd = $byAd->get($ad2->id);

        $this->assertNotNull($firstAd, 'no element for the first ad');
        $this->assertNotNull($secondAd, 'no element for the second ad');

        $this->assertSame(1, $firstAd['conversions']);
        $this->assertSame(1, $secondAd['conversions']);
        $this->assertLessThanOrEqual(1, $eventQueries);
    }

    public function test_it_lists_conversion_elements_with_their_count_and_source_ad_sorted(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $ete = Campaign::factory()->matching('src', 'meta_ete')->create(['name' => 'Été']);
        $ad = Ad::factory()->for($ete)->matching('src', 'meta_ete')->create(['name' => 'Cabrio']);
        AdObjective::factory()->for($ad)->event('Lead')->create();

        foreach (range(1, 2) as $ignored) {
            $visitor = Visitor::factory()->create();
            $session = $this->taggedSession(['src' => 'meta_ete'], $visitor);
            Event::factory()->for($session)->custom('Lead')->create();
        }

        $elements = (new MarketingReportBuilder)->conversionElements(
            Period::ofDays(30),
            null,
            app(FunnelRegistry::class),
            app(EventRegistry::class),
            array_values(Ad::query()->with('objectives')->get()->all()),
        );

        $this->assertCount(1, $elements);
        $this->assertSame('event', $elements[0]['type']);
        $this->assertSame('Lead', $elements[0]['reference']);
        $this->assertSame(2, $elements[0]['conversions']);
        $this->assertSame('Cabrio', $elements[0]['adName']);
        $this->assertNull($elements[0]['steps']);
    }
}
