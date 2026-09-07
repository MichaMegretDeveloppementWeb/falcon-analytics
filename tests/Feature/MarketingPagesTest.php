<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Livewire\Dashboard\AdDetailPage;
use Falcon\Analytics\Livewire\Dashboard\CampaignDetailPage;
use Falcon\Analytics\Livewire\Dashboard\CampaignsPage;
use Falcon\Analytics\Livewire\Dashboard\Widgets\CampaignDetailContent;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\AdObjective;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;

final class MarketingPagesTest extends TestCase
{
    use RefreshDatabase;

    private TestAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = TestAdmin::create([]);
    }

    private function campaign(string $name = 'Été', ?string $platform = null, string $value = 'meta'): Campaign
    {
        return Campaign::create([
            'name' => $name,
            'platform' => $platform,
            'match_conditions' => [['param' => 'src', 'value' => $value]],
        ]);
    }

    public function test_it_mounts_the_marketing_module_at_its_own_prefix_and_route_names(): void
    {
        $this->assertSame('/admin/marketing', route('marketing.dashboard', absolute: false));
        $this->assertSame('/admin/marketing/campaigns', route('marketing.campaigns', absolute: false));
        $this->assertSame('/admin/marketing/campaigns/1', route('marketing.campaigns.show', ['campaign' => 1], absolute: false));
        $this->assertSame('/admin/marketing/ads', route('marketing.ads', absolute: false));
        $this->assertSame('/admin/marketing/ads/1', route('marketing.ads.show', ['ad' => 1], absolute: false));
    }

    public function test_it_protects_the_marketing_module_from_guests(): void
    {
        $campaign = $this->campaign();

        $this->get(route('marketing.dashboard'))->assertRedirect(route('login'));
        $this->get(route('marketing.campaigns'))->assertRedirect(route('login'));
        $this->get(route('marketing.campaigns.show', $campaign))->assertRedirect(route('login'));
        $this->get(route('marketing.ads'))->assertRedirect(route('login'));
    }

    public function test_it_defers_the_campaign_performance_and_dispatches_the_ads_table_metrics(): void
    {
        $campaign = $this->campaign();

        $this->actingAs($this->admin, 'admin');

        Livewire::test(CampaignDetailContent::class, ['refId' => $campaign->id, 'period' => 30])
            ->call('$refresh')
            ->assertDispatched('campaign-metrics-loaded')
            ->assertSeeText(__('Sessions'))
            ->assertSeeText(__('Taux de conversion'));
    }

    public function test_it_fills_its_inline_ads_table_metrics_from_the_dispatched_event(): void
    {
        $campaign = $this->campaign();

        $this->actingAs($this->admin, 'admin');

        Livewire::test(CampaignDetailPage::class, ['campaign' => $campaign])
            ->assertSet('adMetrics', [])
            ->call('fillAdMetrics', [7 => ['sessions' => 12, 'visitors' => 9]], [7 => 4])
            ->assertSet('adMetrics', [7 => ['sessions' => 12, 'visitors' => 9]])
            ->assertSet('adConversions', [7 => 4]);
    }

    public function test_it_renders_the_four_marketing_screens_for_an_admin(): void
    {
        $campaign = $this->campaign('Été 2026', 'Meta', 'meta_ete');
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
    }

    public function test_it_creates_a_campaign_from_the_campaigns_table(): void
    {
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

        $this->assertSame(
            [['param' => 'utm_campaign', 'value' => 'hiver']],
            Campaign::where('name', 'Hiver 2026')->first()?->match_conditions,
        );
    }

    public function test_it_edits_the_campaign_in_place_from_its_detail_page_through_the_shared_form(): void
    {
        $campaign = $this->campaign('Été', 'Meta', 'meta_ete');
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

        $this->assertSame('Été 2027', $fresh->name);
        $this->assertSame([
            ['param' => 'src', 'value' => 'meta_ete'],
            ['param' => 'creative', 'value' => 'cabrio'],
        ], $fresh->match_conditions);
    }

    public function test_it_manages_ads_and_objectives_from_the_campaign_detail_in_one_save(): void
    {
        $campaign = $this->campaign('Été', null, 'meta_ete');
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

        $this->assertSame($campaign->id, $ad->campaign_id);
        $this->assertSame(2, AdObjective::where('ad_id', $ad->id)->count());
    }

    public function test_it_edits_an_ad_and_its_objectives_in_place_from_the_ad_detail(): void
    {
        $campaign = $this->campaign('Été', null, 'meta_ete');
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

        $this->assertSame('Cabriolet décapotable', $ad->fresh()->name);
        $this->assertTrue(AdObjective::where('ad_id', $ad->id)->where('reference', 'Lead')->exists());
    }

    public function test_it_excludes_already_selected_objectives_from_the_pickers(): void
    {
        $campaign = $this->campaign('Été', null, 'meta_ete');
        $this->actingAs($this->admin, 'admin');

        $component = Livewire::test(CampaignDetailPage::class, ['campaign' => $campaign])
            ->call('newAd')
            ->call('addObjective', 'event', 'Lead', 'Lead', 3.0);

        $offered = Collection::make($component->get('eventOptions'))->pluck('reference')->all();

        $this->assertNotContains('Lead', $offered);
    }

    public function test_it_deletes_a_campaign_and_cascades_to_its_ads_and_objectives(): void
    {
        $campaign = $this->campaign('Été', null, 'meta_ete');
        $ad = Ad::create(['campaign_id' => $campaign->id, 'name' => 'Cabrio', 'match_conditions' => [['param' => 'creative', 'value' => 'cabrio']]]);
        AdObjective::create(['ad_id' => $ad->id, 'type' => 'event', 'reference' => 'Lead', 'value' => 3]);

        $other = $this->campaign('Hiver', null, 'meta_hiver');

        $this->actingAs($this->admin, 'admin');

        Livewire::test(CampaignsPage::class)
            ->call('confirmDelete', $campaign->id)
            ->call('deleteConfirmed');

        $this->assertFalse(Campaign::whereKey($campaign->id)->exists());
        $this->assertSame(0, Ad::count());
        $this->assertSame(0, AdObjective::count());
        $this->assertTrue(Campaign::whereKey($other->id)->exists());
    }

    public function test_it_rejects_forged_objective_types_before_they_reach_the_database(): void
    {
        $campaign = $this->campaign('Ete', null, 'meta_ete');
        $this->actingAs($this->admin, 'admin');

        Livewire::test(CampaignDetailPage::class, ['campaign' => $campaign])
            ->call('newAd')
            ->set('adName', 'Cabriolet')
            ->set('adConditions.0.param', 'creative')
            ->set('adConditions.0.value', 'cabrio')
            ->call('addObjective', 'forged-type', 'whatever', 'Whatever')
            ->call('saveAd')
            ->assertHasErrors(['objectives.0.type']);

        $this->assertSame(0, Ad::query()->count());
        $this->assertSame(0, AdObjective::query()->count());
    }

    public function test_it_displays_the_condition_validation_messages_as_text_not_only_a_red_border(): void
    {
        $campaign = $this->campaign('Ete', null, 'meta_ete');
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
    }

    public function test_it_toasts_instead_of_crashing_when_editing_a_record_that_no_longer_exists(): void
    {
        $campaign = $this->campaign('Ete', null, 'meta_ete');
        $this->actingAs($this->admin, 'admin');

        Livewire::test(CampaignsPage::class)
            ->call('editCampaign', 999_999)
            ->assertDispatched('toast', type: 'danger')
            ->assertSet('modal', '');

        Livewire::test(CampaignDetailPage::class, ['campaign' => $campaign])
            ->call('editAd', 999_999)
            ->assertDispatched('toast', type: 'danger')
            ->assertSet('modal', '');
    }
}
