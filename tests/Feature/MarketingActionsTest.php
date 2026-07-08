<?php

use Falcon\Analytics\Actions\DeleteAdAction;
use Falcon\Analytics\Actions\DeleteCampaignAction;
use Falcon\Analytics\Actions\SaveAdAction;
use Falcon\Analytics\Actions\SaveCampaignAction;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\AdObjective;
use Falcon\Analytics\Models\Campaign;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates then updates a campaign', function () {
    $campaign = (new SaveCampaignAction)->execute(null, 'Été', 'Meta', [['param' => 'src', 'value' => 'meta']]);

    expect($campaign->name)->toBe('Été')
        ->and($campaign->platform)->toBe('Meta')
        ->and(Campaign::query()->count())->toBe(1);

    $updated = (new SaveCampaignAction)->execute($campaign->id, 'Été 2026', null, [['param' => 'src', 'value' => 'x']]);

    expect($updated->id)->toBe($campaign->id)
        ->and($updated->name)->toBe('Été 2026')
        ->and($updated->platform)->toBeNull()
        ->and(Campaign::query()->count())->toBe(1);
});

it('saves an ad and rebuilds its objectives in one transaction', function () {
    $campaign = Campaign::create(['name' => 'C', 'match_conditions' => [['param' => 'src', 'value' => 'meta']]]);

    $ad = (new SaveAdAction)->execute(null, $campaign->id, 'Cabrio', [['param' => 'creative', 'value' => 'cabrio']], [
        ['type' => 'event', 'reference' => 'Lead', 'label' => 'Lead'],
        ['type' => 'funnel', 'reference' => 'concours', 'label' => 'Concours'],
    ]);

    expect($ad->name)->toBe('Cabrio')
        ->and($ad->campaign_id)->toBe($campaign->id)
        ->and(AdObjective::query()->where('ad_id', $ad->id)->count())->toBe(2);

    // Re-saving replaces the objective set rather than appending to it.
    (new SaveAdAction)->execute($ad->id, $campaign->id, 'Cabrio', [['param' => 'creative', 'value' => 'cabrio']], [
        ['type' => 'event', 'reference' => 'Lead', 'label' => 'Lead'],
    ]);

    expect(AdObjective::query()->where('ad_id', $ad->id)->count())->toBe(1);
});

it('deletes a campaign with its ads and objectives', function () {
    $campaign = Campaign::create(['name' => 'C', 'match_conditions' => [['param' => 'src', 'value' => 'meta']]]);
    $ad = Ad::create(['campaign_id' => $campaign->id, 'name' => 'A', 'match_conditions' => [['param' => 'x', 'value' => 'y']]]);
    AdObjective::create(['ad_id' => $ad->id, 'type' => 'event', 'reference' => 'Lead']);

    (new DeleteCampaignAction)->execute($campaign->id);

    expect(Campaign::query()->count())->toBe(0)
        ->and(Ad::query()->count())->toBe(0)
        ->and(AdObjective::query()->count())->toBe(0);
});

it('deletes an ad only when it belongs to the given campaign', function () {
    $campaign = Campaign::create(['name' => 'C', 'match_conditions' => [['param' => 'src', 'value' => 'meta']]]);
    $ad = Ad::create(['campaign_id' => $campaign->id, 'name' => 'A', 'match_conditions' => [['param' => 'x', 'value' => 'y']]]);
    AdObjective::create(['ad_id' => $ad->id, 'type' => 'event', 'reference' => 'Lead']);

    // Wrong campaign id: the ad and its objectives stay intact.
    (new DeleteAdAction)->execute($ad->id, $campaign->id + 999);

    expect(Ad::query()->count())->toBe(1)
        ->and(AdObjective::query()->count())->toBe(1);

    (new DeleteAdAction)->execute($ad->id, $campaign->id);

    expect(Ad::query()->count())->toBe(0)
        ->and(AdObjective::query()->count())->toBe(0);
});
