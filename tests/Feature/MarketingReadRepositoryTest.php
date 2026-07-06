<?php

use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Repositories\Dashboard\MarketingReadRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
