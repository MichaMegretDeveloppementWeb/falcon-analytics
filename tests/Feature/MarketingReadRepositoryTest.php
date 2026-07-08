<?php

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
use Falcon\Analytics\Repositories\Dashboard\MarketingReadRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function taggedSession(array $params, ?Visitor $visitor = null): Session
{
    $visitor ??= Visitor::create(['uuid' => (string) Str::uuid(), 'first_seen_at' => now(), 'last_seen_at' => now()]);

    return Session::create([
        'visitor_id' => $visitor->id,
        'started_at' => now(),
        'last_activity_at' => now(),
        'is_bot' => false,
        'mkt_params' => $params,
    ]);
}

it('resolves the most specific ad whose conditions all match the session params', function () {
    $ete = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
    $generic = Ad::create(['campaign_id' => $ete->id, 'name' => 'Été générique', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
    $cabrio = Ad::create(['campaign_id' => $ete->id, 'name' => 'Cabriolet', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete'], ['param' => 'creative', 'value' => 'cabrio']]]);

    $repo = new MarketingReadRepository;

    expect($repo->resolveAd(['src' => 'meta_ete', 'creative' => 'cabrio'])->id)->toBe($cabrio->id)  // 2 conditions win over 1
        ->and($repo->resolveAd(['src' => 'meta_ete', 'creative' => 'other'])->id)->toBe($generic->id)
        ->and($repo->resolveAd(['src' => 'meta_ete'])->id)->toBe($generic->id);
});

it('returns null when a condition is unmet, and resolves the campaign independently', function () {
    $ete = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
    Ad::create(['campaign_id' => $ete->id, 'name' => 'Cabrio', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete'], ['param' => 'creative', 'value' => 'cabrio']]]);

    $repo = new MarketingReadRepository;

    expect($repo->resolveAd(['src' => 'meta_hiver', 'creative' => 'cabrio']))->toBeNull() // src differs
        ->and($repo->resolveAd(['src' => 'meta_ete']))->toBeNull()                        // creative missing
        ->and($repo->resolveAd([]))->toBeNull()
        ->and($repo->resolveCampaign(['src' => 'meta_ete'])->id)->toBe($ete->id)
        ->and($repo->resolveCampaign(['src' => 'meta_hiver']))->toBeNull();
});

it('ignores inactive ads and conditionless definitions', function () {
    $ete = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
    Ad::create(['campaign_id' => $ete->id, 'name' => 'Off', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']], 'is_active' => false]);
    Ad::create(['campaign_id' => $ete->id, 'name' => 'Empty', 'match_conditions' => []]);

    expect((new MarketingReadRepository)->resolveAd(['src' => 'meta_ete']))->toBeNull();
});

it('aggregates ad-driven traffic per campaign and ad over the period', function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

    $ete = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
    $cabrio = Ad::create(['campaign_id' => $ete->id, 'name' => 'Cabrio', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete'], ['param' => 'creative', 'value' => 'cabrio']]]);

    $visitor = Visitor::create(['uuid' => (string) Str::uuid(), 'first_seen_at' => now(), 'last_seen_at' => now()]);
    taggedSession(['src' => 'meta_ete', 'creative' => 'cabrio'], $visitor);
    taggedSession(['src' => 'meta_ete', 'creative' => 'cabrio'], $visitor);
    taggedSession(['src' => 'meta_ete', 'creative' => 'other']);
    taggedSession(['src' => 'other']);

    $repo = new MarketingReadRepository;
    $period = Period::ofDays(30);

    // Only campaign-matched sessions count as ad-driven: the src=other session is excluded.
    expect($repo->headline($period, null))->toBe(['sessions' => 3, 'visitors' => 2]);

    $performance = $repo->performance($period, null);

    expect($performance['campaigns'][$ete->id])->toBe(['sessions' => 3, 'visitors' => 2])
        ->and($performance['ads'][$cabrio->id])->toBe(['sessions' => 2, 'visitors' => 1]);
});

it('credits an ad with a conversion when its visitor completes an event objective', function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

    $ete = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
    $ad = Ad::create(['campaign_id' => $ete->id, 'name' => 'Cabrio', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
    AdObjective::create(['ad_id' => $ad->id, 'type' => 'event', 'reference' => 'Lead', 'value' => 3]);

    $converter = Visitor::create(['uuid' => (string) Str::uuid(), 'first_seen_at' => now(), 'last_seen_at' => now()]);
    $session = taggedSession(['src' => 'meta_ete'], $converter);
    Event::create(['session_id' => $session->id, 'visitor_id' => $converter->id, 'type' => 'custom', 'name' => 'Lead', 'occurred_at' => now()]);

    // A second ad-driven visitor who never fired the event.
    taggedSession(['src' => 'meta_ete']);

    $result = (new MarketingReadRepository)->conversions(Period::ofDays(30), null, app(FunnelRegistry::class));

    expect($result['total'])->toBe(1)
        ->and($result['ads'][$ad->id])->toBe(1)
        ->and($result['campaigns'][$ete->id])->toBe(1)
        ->and($result['objectives'][$ad->id]['Lead'])->toBe(1);
});

it('credits an ad with a conversion when its visitor completes a funnel objective, and reports per-step reach', function () {
    config(['analytics.funnels_path' => __DIR__.'/../Fixtures/analytics-funnels.php']);
    $this->app->forgetInstance(FunnelRegistry::class);
    $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

    $ete = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
    $ad = Ad::create(['campaign_id' => $ete->id, 'name' => 'Cabrio', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
    AdObjective::create(['ad_id' => $ad->id, 'type' => 'funnel', 'reference' => 'sample', 'value' => null]);

    // A visitor tagged to the ad who walks the funnel in order: pageview home, then sample.action.
    $converter = Visitor::create(['uuid' => (string) Str::uuid(), 'first_seen_at' => now(), 'last_seen_at' => now()]);
    $session = taggedSession(['src' => 'meta_ete'], $converter);
    Event::create(['session_id' => $session->id, 'visitor_id' => $converter->id, 'type' => 'pageview', 'route' => 'home', 'occurred_at' => now()->subMinutes(2)]);
    Event::create(['session_id' => $session->id, 'visitor_id' => $converter->id, 'type' => 'custom', 'name' => 'sample.action', 'occurred_at' => now()->subMinute()]);

    // Another ad-driven visitor who only reached the first step (no conversion).
    $halfway = Visitor::create(['uuid' => (string) Str::uuid(), 'first_seen_at' => now(), 'last_seen_at' => now()]);
    $halfSession = taggedSession(['src' => 'meta_ete'], $halfway);
    Event::create(['session_id' => $halfSession->id, 'visitor_id' => $halfway->id, 'type' => 'pageview', 'route' => 'home', 'occurred_at' => now()->subMinutes(2)]);

    $funnels = app(FunnelRegistry::class);
    $result = (new MarketingReadRepository)->conversions(Period::ofDays(30), null, $funnels);

    expect($result['total'])->toBe(1)
        ->and($result['ads'][$ad->id])->toBe(1)
        ->and($result['campaigns'][$ete->id])->toBe(1);

    $elements = (new MarketingReadRepository)->conversionElements(
        Period::ofDays(30),
        null,
        $funnels,
        app(EventRegistry::class),
        Ad::query()->with('objectives')->get()->all(),
    );

    expect($elements)->toHaveCount(1)
        ->and($elements[0]['type'])->toBe('funnel')
        ->and($elements[0]['reference'])->toBe('sample')
        ->and($elements[0]['conversions'])->toBe(1)
        ->and($elements[0]['steps'])->toBe([
            ['label' => 'Viewed', 'count' => 2],
            ['label' => 'Acted', 'count' => 1],
        ]);
});

it('batches event-objective conversions into one query and buckets them per ad', function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

    $ete = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
    $ad1 = Ad::create(['campaign_id' => $ete->id, 'name' => 'A1', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete'], ['param' => 'creative', 'value' => 'c1']]]);
    $ad2 = Ad::create(['campaign_id' => $ete->id, 'name' => 'A2', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete'], ['param' => 'creative', 'value' => 'c2']]]);
    AdObjective::create(['ad_id' => $ad1->id, 'type' => 'event', 'reference' => 'Lead', 'value' => 1]);
    AdObjective::create(['ad_id' => $ad2->id, 'type' => 'event', 'reference' => 'Lead', 'value' => 1]);

    $v1 = Visitor::create(['uuid' => (string) Str::uuid(), 'first_seen_at' => now(), 'last_seen_at' => now()]);
    $s1 = taggedSession(['src' => 'meta_ete', 'creative' => 'c1'], $v1);
    Event::create(['session_id' => $s1->id, 'visitor_id' => $v1->id, 'type' => 'custom', 'name' => 'Lead', 'occurred_at' => now()]);

    $v2 = Visitor::create(['uuid' => (string) Str::uuid(), 'first_seen_at' => now(), 'last_seen_at' => now()]);
    $s2 = taggedSession(['src' => 'meta_ete', 'creative' => 'c2'], $v2);
    Event::create(['session_id' => $s2->id, 'visitor_id' => $v2->id, 'type' => 'custom', 'name' => 'Lead', 'occurred_at' => now()]);

    DB::enableQueryLog();
    $elements = (new MarketingReadRepository)->conversionElements(
        Period::ofDays(30), null, app(FunnelRegistry::class), app(EventRegistry::class),
        Ad::query()->with('objectives')->get()->all(),
    );
    $eventQueries = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'falcon_analytics_events'))->count();
    DB::disableQueryLog();

    $byAd = collect($elements)->keyBy('adId');

    // Each ad is credited only its own visitor's conversion, from a single batched read.
    expect($byAd[$ad1->id]['conversions'])->toBe(1)
        ->and($byAd[$ad2->id]['conversions'])->toBe(1)
        ->and($eventQueries)->toBeLessThanOrEqual(1);
});

it('lists conversion elements with their count and source ad, sorted', function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

    $ete = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
    $ad = Ad::create(['campaign_id' => $ete->id, 'name' => 'Cabrio', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
    AdObjective::create(['ad_id' => $ad->id, 'type' => 'event', 'reference' => 'Lead', 'value' => 3]);

    foreach (range(1, 2) as $ignored) {
        $visitor = Visitor::create(['uuid' => (string) Str::uuid(), 'first_seen_at' => now(), 'last_seen_at' => now()]);
        $session = taggedSession(['src' => 'meta_ete'], $visitor);
        Event::create(['session_id' => $session->id, 'visitor_id' => $visitor->id, 'type' => 'custom', 'name' => 'Lead', 'occurred_at' => now()]);
    }

    $elements = (new MarketingReadRepository)->conversionElements(
        Period::ofDays(30),
        null,
        app(FunnelRegistry::class),
        app(EventRegistry::class),
        Ad::query()->with('objectives')->get()->all(),
    );

    expect($elements)->toHaveCount(1)
        ->and($elements[0]['type'])->toBe('event')
        ->and($elements[0]['reference'])->toBe('Lead')
        ->and($elements[0]['conversions'])->toBe(2)
        ->and($elements[0]['adName'])->toBe('Cabrio')
        ->and($elements[0]['steps'])->toBeNull();
});
