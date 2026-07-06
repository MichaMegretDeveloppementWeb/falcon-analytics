<?php

use Falcon\Analytics\Livewire\Dashboard\CampaignDetailPage;
use Falcon\Analytics\Livewire\Dashboard\CampaignsPage;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\AdObjective;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = TestAdmin::create([]);
});

it('mounts the marketing module at its own prefix and route names', function () {
    expect(route('marketing.dashboard', absolute: false))->toBe('/admin/marketing')
        ->and(route('marketing.campaigns', absolute: false))->toBe('/admin/marketing/campaigns')
        ->and(route('marketing.campaigns.show', ['campaign' => 1], absolute: false))->toBe('/admin/marketing/campaigns/1')
        ->and(route('marketing.ads', absolute: false))->toBe('/admin/marketing/ads')
        ->and(route('marketing.ads.show', ['ad' => 1], absolute: false))->toBe('/admin/marketing/ads/1');
});

it('protects the marketing module from guests', function () {
    $campaign = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta']]]);

    $this->get(route('marketing.dashboard'))->assertRedirect(route('login'));
    $this->get(route('marketing.campaigns'))->assertRedirect(route('login'));
    $this->get(route('marketing.campaigns.show', $campaign))->assertRedirect(route('login'));
    $this->get(route('marketing.ads'))->assertRedirect(route('login'));
});

it('renders the four marketing screens for an admin', function () {
    $campaign = Campaign::create(['name' => 'Été 2026', 'platform' => 'Meta', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
    Ad::create(['campaign_id' => $campaign->id, 'name' => 'Cabriolet', 'match_conditions' => [['param' => 'creative', 'value' => 'cabrio']]]);

    $this->actingAs($this->admin, 'admin');

    $this->get(route('marketing.dashboard'))->assertSuccessful()->assertSeeText(__('Vue d\'ensemble'))->assertSeeText(__('Sessions issues de pubs'));
    $this->get(route('marketing.campaigns'))->assertSuccessful()->assertSeeText('Été 2026')->assertSeeText('meta_ete');
    $this->get(route('marketing.campaigns.show', $campaign))->assertSuccessful()->assertSeeText('Cabriolet')->assertSeeText('cabrio');
    $this->get(route('marketing.ads'))->assertSuccessful()->assertSeeText('Cabriolet');

    $ad = Ad::where('name', 'Cabriolet')->firstOrFail();
    $this->get(route('marketing.ads.show', $ad))->assertSuccessful()
        ->assertSeeText('Cabriolet')
        ->assertSeeText('Été 2026')
        ->assertSeeText(__('Performance'));
});

it('creates a campaign from the campaigns table', function () {
    $this->actingAs($this->admin, 'admin');

    Livewire::test(CampaignsPage::class)
        ->call('newCampaign')
        ->set('campaignName', 'Hiver 2026')
        ->set('campaignPlatform', 'Google')
        ->set('campaignConditions.0.param', 'utm_campaign')
        ->set('campaignConditions.0.value', 'hiver')
        ->call('saveCampaign')
        ->assertHasNoErrors()
        ->assertSet('modal', '');

    expect(Campaign::where('name', 'Hiver 2026')->first()?->match_conditions)->toBe([['param' => 'utm_campaign', 'value' => 'hiver']]);
});

it('manages ads and objectives from the campaign detail, in one save', function () {
    $campaign = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
    $this->actingAs($this->admin, 'admin');

    Livewire::test(CampaignDetailPage::class, ['campaign' => $campaign])
        ->call('newAd')
        ->set('adName', 'Cabriolet')
        ->set('adConditions.0.param', 'creative')
        ->set('adConditions.0.value', 'cabrio')
        ->call('addObjective', 'funnel', 'concours', 'Concours')
        ->call('addObjective', 'event', 'Lead', 'Demande de code', 3.0)
        ->call('saveAd')
        ->assertHasNoErrors()
        ->assertSet('modal', '');

    $ad = Ad::where('name', 'Cabriolet')->firstOrFail();

    expect($ad->campaign_id)->toBe($campaign->id)
        ->and(AdObjective::where('ad_id', $ad->id)->count())->toBe(2);
});

it('excludes already-selected objectives from the pickers', function () {
    $campaign = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
    $this->actingAs($this->admin, 'admin');

    $component = Livewire::test(CampaignDetailPage::class, ['campaign' => $campaign])
        ->call('newAd')
        ->call('addObjective', 'event', 'Lead', 'Lead', 3.0);

    $offered = collect($component->get('eventOptions'))->pluck('reference')->all();

    expect($offered)->not->toContain('Lead');
});

it('deletes a campaign and cascades to its ads and objectives', function () {
    $campaign = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
    $ad = Ad::create(['campaign_id' => $campaign->id, 'name' => 'Cabrio', 'match_conditions' => [['param' => 'creative', 'value' => 'cabrio']]]);
    AdObjective::create(['ad_id' => $ad->id, 'type' => 'event', 'reference' => 'Lead', 'value' => 3]);

    $other = Campaign::create(['name' => 'Hiver', 'match_conditions' => [['param' => 'src', 'value' => 'meta_hiver']]]);

    $this->actingAs($this->admin, 'admin');

    Livewire::test(CampaignsPage::class)
        ->call('confirmDelete', $campaign->id)
        ->call('deleteConfirmed');

    expect(Campaign::whereKey($campaign->id)->exists())->toBeFalse()
        ->and(Ad::count())->toBe(0)
        ->and(AdObjective::count())->toBe(0)
        ->and(Campaign::whereKey($other->id)->exists())->toBeTrue();
});
