<?php

use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Repositories\Dashboard\MarketingReadRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves a session to the right ad, with no collision when an ad key is reused across campaigns', function () {
    $ete = Campaign::create(['name' => 'Été', 'key' => 'ete']);
    $hiver = Campaign::create(['name' => 'Hiver', 'key' => 'hiver']);
    $eteCabrio = Ad::create(['campaign_id' => $ete->id, 'name' => 'Été · Cabriolet', 'key' => 'cabrio']);
    $hiverCabrio = Ad::create(['campaign_id' => $hiver->id, 'name' => 'Hiver · Cabriolet', 'key' => 'cabrio']);
    $eteSuv = Ad::create(['campaign_id' => $ete->id, 'name' => 'Été · SUV', 'key' => 'suv']);

    $repo = new MarketingReadRepository;

    expect($repo->resolveAd('ete', 'cabrio')->id)->toBe($eteCabrio->id)
        ->and($repo->resolveAd('hiver', 'cabrio')->id)->toBe($hiverCabrio->id)
        ->and($repo->resolveAd('ete', 'suv')->id)->toBe($eteSuv->id);
});

it('returns null for unknown, partial or empty ad tags', function () {
    $ete = Campaign::create(['name' => 'Été', 'key' => 'ete']);
    Ad::create(['campaign_id' => $ete->id, 'name' => 'Cabrio', 'key' => 'cabrio']);

    $repo = new MarketingReadRepository;

    expect($repo->resolveAd('ete', 'unknown'))->toBeNull()        // campaign defined, ad not
        ->and($repo->resolveAd('unknown', 'cabrio'))->toBeNull()  // campaign not defined
        ->and($repo->resolveAd('ete', null))->toBeNull()          // campaign-only traffic
        ->and($repo->resolveAd(null, 'cabrio'))->toBeNull()       // no campaign value
        ->and($repo->resolveAd(null, null))->toBeNull();
});
