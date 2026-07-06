<?php

use Falcon\Analytics\Livewire\Dashboard\MarketingSettingsPage;
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

it('exposes the marketing settings route and protects it from guests', function () {
    expect(route('analytics.marketing.settings', absolute: false))->toBe('/admin/analytics/marketing/settings');

    $this->get(route('analytics.marketing.settings'))->assertRedirect(route('login'));
});

it('renders the marketing settings for an admin', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(route('analytics.marketing.settings'))
        ->assertSuccessful()
        ->assertSeeText(__('Marketing'));
});

it('creates a campaign with URL conditions', function () {
    $this->actingAs($this->admin, 'admin');

    Livewire::test(MarketingSettingsPage::class)
        ->call('newCampaign')
        ->set('campaignName', 'Été 2026')
        ->set('campaignPlatform', 'Meta')
        ->set('campaignConditions.0.param', 'src')
        ->set('campaignConditions.0.value', 'meta_ete')
        ->call('saveCampaign')
        ->assertHasNoErrors()
        ->assertSet('modal', '');

    $campaign = Campaign::where('name', 'Été 2026')->firstOrFail();

    expect($campaign->match_conditions)->toBe([['param' => 'src', 'value' => 'meta_ete']])
        ->and($campaign->platform)->toBe('Meta');
});

it('validates the campaign name and requires at least one condition', function () {
    $this->actingAs($this->admin, 'admin');

    Livewire::test(MarketingSettingsPage::class)
        ->call('newCampaign')
        ->set('campaignName', '')
        ->set('campaignConditions.0.param', '')
        ->set('campaignConditions.0.value', '')
        ->call('saveCampaign')
        ->assertHasErrors(['campaignName', 'campaignConditions.0.param', 'campaignConditions.0.value']);

    expect(Campaign::count())->toBe(0);
});

it('creates an ad with conditions then adds funnel and event objectives', function () {
    $campaign = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
    $this->actingAs($this->admin, 'admin');

    $component = Livewire::test(MarketingSettingsPage::class)
        ->call('newAd', $campaign->id)
        ->set('adName', 'Cabriolet')
        ->set('adConditions.0.param', 'creative')
        ->set('adConditions.0.value', 'cabrio')
        ->call('saveAd')
        ->assertHasNoErrors();

    $ad = Ad::where('name', 'Cabriolet')->firstOrFail();

    expect($ad->match_conditions)->toBe([['param' => 'creative', 'value' => 'cabrio']]);

    $component->call('selectObjective', 'funnel', 'concours')->call('addObjective')->assertHasNoErrors();
    $component->call('selectObjective', 'event', 'Lead', 3.0)->call('addObjective')->assertHasNoErrors();

    expect(AdObjective::where('ad_id', $ad->id)->count())->toBe(2)
        ->and((float) AdObjective::where('ad_id', $ad->id)->where('type', 'event')->value('value'))->toBe(3.0);
});

it('requires a value for an event objective', function () {
    $campaign = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
    $ad = Ad::create(['campaign_id' => $campaign->id, 'name' => 'Cabrio', 'match_conditions' => [['param' => 'creative', 'value' => 'cabrio']]]);
    $this->actingAs($this->admin, 'admin');

    Livewire::test(MarketingSettingsPage::class)
        ->call('editAd', $ad->id)
        ->call('selectObjective', 'event', 'Lead')
        ->set('objValue', '')
        ->call('addObjective')
        ->assertHasErrors('objValue');
});

it('removes an objective', function () {
    $campaign = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
    $ad = Ad::create(['campaign_id' => $campaign->id, 'name' => 'Cabrio', 'match_conditions' => [['param' => 'creative', 'value' => 'cabrio']]]);
    $objective = AdObjective::create(['ad_id' => $ad->id, 'type' => 'event', 'reference' => 'Lead', 'value' => 3]);

    $this->actingAs($this->admin, 'admin');

    Livewire::test(MarketingSettingsPage::class)
        ->call('editAd', $ad->id)
        ->call('removeObjective', $objective->id);

    expect(AdObjective::count())->toBe(0);
});

it('deletes a campaign and cascades to its ads and objectives, leaving others untouched', function () {
    $campaign = Campaign::create(['name' => 'Été', 'match_conditions' => [['param' => 'src', 'value' => 'meta_ete']]]);
    $ad = Ad::create(['campaign_id' => $campaign->id, 'name' => 'Cabrio', 'match_conditions' => [['param' => 'creative', 'value' => 'cabrio']]]);
    AdObjective::create(['ad_id' => $ad->id, 'type' => 'event', 'reference' => 'Lead', 'value' => 3]);

    $other = Campaign::create(['name' => 'Hiver', 'match_conditions' => [['param' => 'src', 'value' => 'meta_hiver']]]);
    Ad::create(['campaign_id' => $other->id, 'name' => 'SUV', 'match_conditions' => [['param' => 'creative', 'value' => 'suv']]]);

    $this->actingAs($this->admin, 'admin');

    Livewire::test(MarketingSettingsPage::class)
        ->call('confirmDelete', 'campaign', $campaign->id)
        ->assertSet('modal', 'delete')
        ->call('deleteConfirmed')
        ->assertSet('modal', '');

    expect(Campaign::whereKey($campaign->id)->exists())->toBeFalse()
        ->and(Ad::where('campaign_id', $campaign->id)->count())->toBe(0)
        ->and(AdObjective::count())->toBe(0)
        ->and(Campaign::whereKey($other->id)->exists())->toBeTrue()
        ->and(Ad::where('campaign_id', $other->id)->count())->toBe(1);
});
