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
        ->assertSeeText(__('Publicités'));
});

it('creates a campaign', function () {
    $this->actingAs($this->admin, 'admin');

    Livewire::test(MarketingSettingsPage::class)
        ->call('newCampaign')
        ->set('campaignName', 'Été 2026')
        ->set('campaignKey', 'ete')
        ->set('campaignPlatform', 'Meta')
        ->call('saveCampaign')
        ->assertHasNoErrors()
        ->assertSet('modal', '');

    expect(Campaign::where('key', 'ete')->where('platform', 'Meta')->exists())->toBeTrue();
});

it('validates the campaign and rejects a duplicate key', function () {
    Campaign::create(['name' => 'Déjà', 'key' => 'ete']);
    $this->actingAs($this->admin, 'admin');

    Livewire::test(MarketingSettingsPage::class)
        ->set('campaignName', '')
        ->set('campaignKey', 'ete')
        ->call('saveCampaign')
        ->assertHasErrors(['campaignName' => 'required', 'campaignKey' => 'unique']);

    expect(Campaign::count())->toBe(1);
});

it('creates an ad then adds funnel and event objectives', function () {
    $campaign = Campaign::create(['name' => 'Été', 'key' => 'ete']);
    $this->actingAs($this->admin, 'admin');

    $component = Livewire::test(MarketingSettingsPage::class)
        ->call('newAd', $campaign->id)
        ->set('adName', 'Cabriolet')
        ->set('adKey', 'cabrio')
        ->call('saveAd')
        ->assertHasNoErrors();

    $ad = Ad::where('key', 'cabrio')->firstOrFail();

    $component->set('objType', 'funnel')->set('objReference', 'concours')->call('addObjective')->assertHasNoErrors();
    $component->set('objType', 'event')->set('objReference', 'Lead')->set('objValue', '3')->call('addObjective')->assertHasNoErrors();

    expect(AdObjective::where('ad_id', $ad->id)->count())->toBe(2)
        ->and((float) AdObjective::where('ad_id', $ad->id)->where('type', 'event')->value('value'))->toBe(3.0);
});

it('requires a value for an event objective but not a funnel one', function () {
    $campaign = Campaign::create(['name' => 'Été', 'key' => 'ete']);
    $ad = Ad::create(['campaign_id' => $campaign->id, 'name' => 'Cabrio', 'key' => 'cabrio']);
    $this->actingAs($this->admin, 'admin');

    Livewire::test(MarketingSettingsPage::class)
        ->call('editAd', $ad->id)
        ->set('objType', 'event')
        ->set('objReference', 'Lead')
        ->set('objValue', '')
        ->call('addObjective')
        ->assertHasErrors('objValue');
});

it('removes an objective', function () {
    $campaign = Campaign::create(['name' => 'Été', 'key' => 'ete']);
    $ad = Ad::create(['campaign_id' => $campaign->id, 'name' => 'Cabrio', 'key' => 'cabrio']);
    $objective = AdObjective::create(['ad_id' => $ad->id, 'type' => 'event', 'reference' => 'Lead', 'value' => 3]);

    $this->actingAs($this->admin, 'admin');

    Livewire::test(MarketingSettingsPage::class)
        ->call('editAd', $ad->id)
        ->call('removeObjective', $objective->id);

    expect(AdObjective::count())->toBe(0);
});

it('deletes a campaign and cascades to its ads and objectives, leaving others untouched', function () {
    $campaign = Campaign::create(['name' => 'Été', 'key' => 'ete']);
    $ad = Ad::create(['campaign_id' => $campaign->id, 'name' => 'Cabrio', 'key' => 'cabrio']);
    AdObjective::create(['ad_id' => $ad->id, 'type' => 'event', 'reference' => 'Lead', 'value' => 3]);

    $other = Campaign::create(['name' => 'Hiver', 'key' => 'hiver']);
    Ad::create(['campaign_id' => $other->id, 'name' => 'SUV', 'key' => 'suv']);

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
