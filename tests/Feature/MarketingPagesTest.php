<?php

use Falcon\Analytics\Livewire\Dashboard\AdDetailPage;
use Falcon\Analytics\Livewire\Dashboard\CampaignDetailPage;
use Falcon\Analytics\Livewire\Dashboard\CampaignsPage;
use Falcon\Analytics\Livewire\Dashboard\Widgets\CampaignDetailContent;
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

it('defers the campaign performance and dispatches the ads table metrics', function () {
    $campaign = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta']]]);

    $this->actingAs($this->admin, 'admin');

    Livewire::test(CampaignDetailContent::class, ['refId' => $campaign->id, 'period' => 30])
        ->call('$refresh')
        ->assertDispatched('campaign-metrics-loaded')
        ->assertSeeText(__('Sessions'))
        ->assertSeeText(__('Taux de conversion'));
});

it('fills its inline ads table metrics from the dispatched event', function () {
    $campaign = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta']]]);

    $this->actingAs($this->admin, 'admin');

    Livewire::test(CampaignDetailPage::class, ['campaign' => $campaign])
        ->assertSet('adMetrics', [])
        ->call('fillAdMetrics', [7 => ['sessions' => 12, 'visitors' => 9]], [7 => 4])
        ->assertSet('adMetrics', [7 => ['sessions' => 12, 'visitors' => 9]])
        ->assertSet('adConversions', [7 => 4]);
});

it('renders the four marketing screens for an admin', function () {
    $campaign = Campaign::create(['name' => 'Été 2026', 'platform' => 'Meta', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
    Ad::create(['campaign_id' => $campaign->id, 'name' => 'Cabriolet', 'match_conditions' => [['param' => 'creative', 'value' => 'cabrio']]]);

    $this->actingAs($this->admin, 'admin');

    $this->get(route('marketing.dashboard'))->assertSuccessful()->assertSeeText(__('Vue d\'ensemble'));
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

it('edits the campaign in place from its detail page, through the shared form', function () {
    $campaign = Campaign::create(['name' => 'Été', 'platform' => 'Meta', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
    $this->actingAs($this->admin, 'admin');

    Livewire::test(CampaignDetailPage::class, ['campaign' => $campaign])
        ->call('editCampaign')
        ->assertSet('modal', 'campaign')
        ->assertSet('campaignName', 'Été')
        ->assertSet('campaignConditions', [['param' => 'src', 'value' => 'meta_ete']])
        ->set('campaignName', 'Été 2027')
        ->call('addCampaignCondition')
        ->set('campaignConditions.1.param', 'creative')
        ->set('campaignConditions.1.value', 'cabrio')
        ->call('saveCampaign')
        ->assertHasNoErrors()
        ->assertSet('modal', '');

    $fresh = $campaign->fresh();

    expect($fresh->name)->toBe('Été 2027')
        ->and($fresh->match_conditions)->toBe([
            ['param' => 'src', 'value' => 'meta_ete'],
            ['param' => 'creative', 'value' => 'cabrio'],
        ]);
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

it('edits an ad and its objectives in place from the ad detail', function () {
    $campaign = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
    $ad = Ad::create(['campaign_id' => $campaign->id, 'name' => 'Cabrio', 'match_conditions' => [['param' => 'creative', 'value' => 'cabrio']]]);
    $this->actingAs($this->admin, 'admin');

    Livewire::test(AdDetailPage::class, ['ad' => $ad])
        ->call('editAd')
        ->assertSet('modal', 'ad')
        ->set('adName', 'Cabriolet décapotable')
        ->call('addObjective', 'event', 'Lead', 'Lead', 2.0)
        ->call('saveAd')
        ->assertHasNoErrors()
        ->assertSet('modal', '');

    expect($ad->fresh()->name)->toBe('Cabriolet décapotable')
        ->and(AdObjective::where('ad_id', $ad->id)->where('reference', 'Lead')->exists())->toBeTrue();
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

it('rejects forged objective types before they reach the database', function () {
    $campaign = Campaign::create(['name' => 'Ete', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
    $this->actingAs($this->admin, 'admin');

    Livewire::test(CampaignDetailPage::class, ['campaign' => $campaign])
        ->call('newAd')
        ->set('adName', 'Cabriolet')
        ->set('adConditions.0.param', 'creative')
        ->set('adConditions.0.value', 'cabrio')
        ->call('addObjective', 'forged-type', 'whatever', 'Whatever')
        ->call('saveAd')
        ->assertHasErrors(['objectives.0.type']);

    expect(Ad::query()->count())->toBe(0)
        ->and(AdObjective::query()->count())->toBe(0);
});

it('displays the condition validation messages as text, not only a red border', function () {
    $campaign = Campaign::create(['name' => 'Ete', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
    $this->actingAs($this->admin, 'admin');

    Livewire::test(CampaignDetailPage::class, ['campaign' => $campaign])
        ->call('newAd')
        ->set('adName', 'Cabriolet')
        ->set('adConditions.0.param', '')
        ->set('adConditions.0.value', '')
        ->call('saveAd')
        ->assertHasErrors(['adConditions.0.param', 'adConditions.0.value'])
        ->assertSeeText(__('Le paramètre est obligatoire.'));

    Livewire::test(CampaignsPage::class)
        ->call('newCampaign')
        ->set('campaignName', 'Hiver')
        ->set('campaignConditions.0.param', '')
        ->set('campaignConditions.0.value', 'x')
        ->call('saveCampaign')
        ->assertHasErrors(['campaignConditions.0.param'])
        ->assertSeeText(__('Le paramètre est obligatoire.'));
});

it('toasts instead of crashing when editing a campaign or ad that no longer exists', function () {
    $campaign = Campaign::create(['name' => 'Ete', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
    $this->actingAs($this->admin, 'admin');

    Livewire::test(CampaignsPage::class)
        ->call('editCampaign', 999_999)
        ->assertDispatched('toast', type: 'danger')
        ->assertSet('modal', '');

    Livewire::test(CampaignDetailPage::class, ['campaign' => $campaign])
        ->call('editAd', 999_999)
        ->assertDispatched('toast', type: 'danger')
        ->assertSet('modal', '');
});
