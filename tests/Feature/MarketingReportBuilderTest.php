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
use Illuminate\Support\Str;

final class MarketingReportBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function newVisitor(): Visitor
    {
        return Visitor::create(['uuid' => (string) Str::uuid(), 'first_seen_at' => now(), 'last_seen_at' => now()]);
    }

    /**
     * @param  array<string, string>  $params
     */
    private function taggedSession(array $params, ?Visitor $visitor = null): Session
    {
        $visitor ??= $this->newVisitor();

        return Session::create([
            'visitor_id' => $visitor->id,
            'started_at' => now(),
            'last_activity_at' => now(),
            'is_bot' => false,
            'mkt_params' => $params,
        ]);
    }

    public function test_it_resolves_the_most_specific_ad_whose_conditions_all_match_the_session_params(): void
    {
        $ete = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
        $generic = Ad::create(['campaign_id' => $ete->id, 'name' => 'Été générique', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
        $cabrio = Ad::create(['campaign_id' => $ete->id, 'name' => 'Cabriolet', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete'], ['param' => 'creative', 'value' => 'cabrio']]]);

        $builder = new MarketingReportBuilder;

        // Deux conditions l'emportent sur une.
        $this->assertSame($cabrio->id, $builder->resolveAd(['src' => 'meta_ete', 'creative' => 'cabrio'])->id);
        $this->assertSame($generic->id, $builder->resolveAd(['src' => 'meta_ete', 'creative' => 'other'])->id);
        $this->assertSame($generic->id, $builder->resolveAd(['src' => 'meta_ete'])->id);
    }

    public function test_it_returns_null_when_a_condition_is_unmet_and_resolves_the_campaign_independently(): void
    {
        $ete = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
        Ad::create(['campaign_id' => $ete->id, 'name' => 'Cabrio', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete'], ['param' => 'creative', 'value' => 'cabrio']]]);

        $builder = new MarketingReportBuilder;

        $this->assertNull($builder->resolveAd(['src' => 'meta_hiver', 'creative' => 'cabrio']), 'src diffère');
        $this->assertNull($builder->resolveAd(['src' => 'meta_ete']), 'creative manque');
        $this->assertNull($builder->resolveAd([]));
        $this->assertSame($ete->id, $builder->resolveCampaign(['src' => 'meta_ete'])->id);
        $this->assertNull($builder->resolveCampaign(['src' => 'meta_hiver']));
    }

    public function test_it_ignores_inactive_ads_and_conditionless_definitions(): void
    {
        $ete = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
        Ad::create(['campaign_id' => $ete->id, 'name' => 'Off', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']], 'is_active' => false]);
        Ad::create(['campaign_id' => $ete->id, 'name' => 'Empty', 'match_conditions' => []]);

        $this->assertNull((new MarketingReportBuilder)->resolveAd(['src' => 'meta_ete']));
    }

    public function test_it_aggregates_ad_driven_traffic_per_campaign_and_ad_over_the_period(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $ete = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
        $cabrio = Ad::create(['campaign_id' => $ete->id, 'name' => 'Cabrio', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete'], ['param' => 'creative', 'value' => 'cabrio']]]);

        $visitor = $this->newVisitor();
        $this->taggedSession(['src' => 'meta_ete', 'creative' => 'cabrio'], $visitor);
        $this->taggedSession(['src' => 'meta_ete', 'creative' => 'cabrio'], $visitor);
        $this->taggedSession(['src' => 'meta_ete', 'creative' => 'other']);
        $this->taggedSession(['src' => 'other']);

        $builder = new MarketingReportBuilder;
        $period = Period::ofDays(30);

        // Seules les sessions rattachées à une campagne comptent comme trafic
        // publicitaire · la session src=other est écartée.
        $this->assertSame(['sessions' => 3, 'visitors' => 2], $builder->headline($period, null));

        $performance = $builder->performance($period, null);

        $this->assertSame(['sessions' => 3, 'visitors' => 2], $performance['campaigns'][$ete->id]);
        $this->assertSame(['sessions' => 2, 'visitors' => 1], $performance['ads'][$cabrio->id]);
    }

    public function test_it_credits_an_ad_with_a_conversion_when_its_visitor_completes_an_event_objective(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $ete = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
        $ad = Ad::create(['campaign_id' => $ete->id, 'name' => 'Cabrio', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
        AdObjective::create(['ad_id' => $ad->id, 'type' => 'event', 'reference' => 'Lead']);

        $converter = $this->newVisitor();
        $session = $this->taggedSession(['src' => 'meta_ete'], $converter);
        Event::create(['session_id' => $session->id, 'visitor_id' => $converter->id, 'type' => 'custom', 'name' => 'Lead', 'occurred_at' => now()]);

        // Un second visiteur venu par la publicité, qui n'a jamais déclenché l'événement.
        $this->taggedSession(['src' => 'meta_ete']);

        $result = (new MarketingReportBuilder)->conversions(Period::ofDays(30), null, app(FunnelRegistry::class));

        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['ads'][$ad->id]);
        $this->assertSame(1, $result['campaigns'][$ete->id]);
        $this->assertSame(1, $result['objectives'][$ad->id]['Lead']);
    }

    public function test_it_credits_a_funnel_objective_and_reports_per_step_reach(): void
    {
        config(['analytics.funnels_path' => __DIR__.'/../Fixtures/analytics-funnels.php']);
        $this->app->forgetInstance(FunnelRegistry::class);
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $ete = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
        $ad = Ad::create(['campaign_id' => $ete->id, 'name' => 'Cabrio', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
        AdObjective::create(['ad_id' => $ad->id, 'type' => 'funnel', 'reference' => 'sample']);

        // Un visiteur rattaché à la publicité, qui parcourt le tunnel dans
        // l'ordre · page vue home, puis sample.action.
        $converter = $this->newVisitor();
        $session = $this->taggedSession(['src' => 'meta_ete'], $converter);
        Event::create(['session_id' => $session->id, 'visitor_id' => $converter->id, 'type' => 'pageview', 'route' => 'home', 'occurred_at' => now()->subMinutes(2)]);
        Event::create(['session_id' => $session->id, 'visitor_id' => $converter->id, 'type' => 'custom', 'name' => 'sample.action', 'occurred_at' => now()->subMinute()]);

        // Un autre visiteur venu par la publicité, qui n'atteint que la
        // première étape · pas de conversion.
        $halfway = $this->newVisitor();
        $halfSession = $this->taggedSession(['src' => 'meta_ete'], $halfway);
        Event::create(['session_id' => $halfSession->id, 'visitor_id' => $halfway->id, 'type' => 'pageview', 'route' => 'home', 'occurred_at' => now()->subMinutes(2)]);

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
            Ad::query()->with('objectives')->get()->all(),
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

        $ete = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
        $ad1 = Ad::create(['campaign_id' => $ete->id, 'name' => 'A1', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete'], ['param' => 'creative', 'value' => 'c1']]]);
        $ad2 = Ad::create(['campaign_id' => $ete->id, 'name' => 'A2', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete'], ['param' => 'creative', 'value' => 'c2']]]);
        AdObjective::create(['ad_id' => $ad1->id, 'type' => 'event', 'reference' => 'Lead']);
        AdObjective::create(['ad_id' => $ad2->id, 'type' => 'event', 'reference' => 'Lead']);

        $v1 = $this->newVisitor();
        $s1 = $this->taggedSession(['src' => 'meta_ete', 'creative' => 'c1'], $v1);
        Event::create(['session_id' => $s1->id, 'visitor_id' => $v1->id, 'type' => 'custom', 'name' => 'Lead', 'occurred_at' => now()]);

        $v2 = $this->newVisitor();
        $s2 = $this->taggedSession(['src' => 'meta_ete', 'creative' => 'c2'], $v2);
        Event::create(['session_id' => $s2->id, 'visitor_id' => $v2->id, 'type' => 'custom', 'name' => 'Lead', 'occurred_at' => now()]);

        DB::enableQueryLog();

        $elements = (new MarketingReportBuilder)->conversionElements(
            Period::ofDays(30), null, app(FunnelRegistry::class), app(EventRegistry::class),
            Ad::query()->with('objectives')->get()->all(),
        );

        $eventQueries = Collection::make(DB::getQueryLog())
            ->filter(fn (array $q): bool => str_contains($q['query'], 'falcon_analytics_events'))
            ->count();

        DB::disableQueryLog();

        $byAd = Collection::make($elements)->keyBy('adId');

        // Chaque publicité n'est créditée que de la conversion de son propre
        // visiteur, depuis une seule lecture groupée.
        $this->assertSame(1, $byAd[$ad1->id]['conversions']);
        $this->assertSame(1, $byAd[$ad2->id]['conversions']);
        $this->assertLessThanOrEqual(1, $eventQueries);
    }

    public function test_it_lists_conversion_elements_with_their_count_and_source_ad_sorted(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $ete = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
        $ad = Ad::create(['campaign_id' => $ete->id, 'name' => 'Cabrio', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
        AdObjective::create(['ad_id' => $ad->id, 'type' => 'event', 'reference' => 'Lead']);

        foreach (range(1, 2) as $ignored) {
            $visitor = $this->newVisitor();
            $session = $this->taggedSession(['src' => 'meta_ete'], $visitor);
            Event::create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'type' => 'custom', 'name' => 'Lead', 'occurred_at' => now()]);
        }

        $elements = (new MarketingReportBuilder)->conversionElements(
            Period::ofDays(30),
            null,
            app(FunnelRegistry::class),
            app(EventRegistry::class),
            Ad::query()->with('objectives')->get()->all(),
        );

        $this->assertCount(1, $elements);
        $this->assertSame('event', $elements[0]['type']);
        $this->assertSame('Lead', $elements[0]['reference']);
        $this->assertSame(2, $elements[0]['conversions']);
        $this->assertSame('Cabrio', $elements[0]['adName']);
        $this->assertNull($elements[0]['steps']);
    }
}
