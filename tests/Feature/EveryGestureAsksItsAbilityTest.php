<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Enums\Authorization\Ability;
use Falcon\Analytics\Livewire\Admin\AdForm;
use Falcon\Analytics\Livewire\Admin\CampaignDetailPage;
use Falcon\Analytics\Livewire\Admin\CampaignForm;
use Falcon\Analytics\Livewire\Admin\CampaignsPage;
use Falcon\Analytics\Livewire\Admin\IntegrationsPage;
use Falcon\Analytics\Livewire\Admin\VisitorDetailPage;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Models\SearchConsoleConnection;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/**
 * A gesture is refused by the method itself, whatever the screen shows: a
 * button hidden in the page is no protection against a call sent without it.
 */
final class EveryGestureAsksItsAbilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(TestAdmin::create([]), 'admin');
    }

    public function test_opening_a_new_campaign_asks_to_edit_campaigns(): void
    {
        $this->refuse(Ability::CampaignsEdit);

        Livewire::test(CampaignForm::class)->call('editCampaign')->assertForbidden();
    }

    public function test_opening_a_campaign_asks_to_edit_it(): void
    {
        $campaign = Campaign::factory()->create();
        $this->refuse(Ability::CampaignsEdit);

        Livewire::test(CampaignForm::class)->call('editCampaign', $campaign->id)->assertForbidden();
    }

    public function test_saving_a_campaign_asks_to_edit_it_and_writes_nothing(): void
    {
        $this->refuse(Ability::CampaignsEdit);

        Livewire::test(CampaignForm::class)
            ->set('campaignName', 'Printemps')
            ->set('campaignConditions', [['param' => 'utm_campaign', 'value' => 'printemps']])
            ->call('saveCampaign')
            ->assertForbidden();

        $this->assertSame(0, Campaign::query()->count());
    }

    public function test_deleting_a_campaign_from_the_list_asks_to_delete_it_and_deletes_nothing(): void
    {
        $campaign = Campaign::factory()->create();
        $this->refuse(Ability::CampaignsDelete);

        Livewire::test(CampaignsPage::class)->call('confirmDelete', $campaign->id)->assertForbidden();
        Livewire::test(CampaignsPage::class)->set('deleteId', $campaign->id)->call('deleteConfirmed')->assertForbidden();

        $this->assertTrue(Campaign::query()->whereKey($campaign->id)->exists());
    }

    public function test_deleting_a_campaign_from_its_page_asks_to_delete_it_and_deletes_nothing(): void
    {
        $campaign = Campaign::factory()->create();
        $this->refuse(Ability::CampaignsDelete);

        Livewire::test(CampaignDetailPage::class, ['campaign' => $campaign])->call('deleteCampaignConfirmed')->assertForbidden();

        $this->assertTrue(Campaign::query()->whereKey($campaign->id)->exists());
    }

    public function test_the_campaign_reaches_the_rule_that_decides_its_deletion(): void
    {
        $kept = Campaign::factory()->create(['name' => 'Automne']);
        $deleted = Campaign::factory()->create(['name' => 'Printemps']);

        Gate::define(Ability::CampaignsDelete, fn (TestAdmin $admin, ?Campaign $campaign = null): bool => $campaign?->name === 'Printemps');

        Livewire::test(CampaignsPage::class)->call('confirmDelete', $kept->id)->assertForbidden();
        Livewire::test(CampaignsPage::class)->call('confirmDelete', $deleted->id)->call('deleteConfirmed')->assertReturned(true);

        $this->assertTrue(Campaign::query()->whereKey($kept->id)->exists());
        $this->assertFalse(Campaign::query()->whereKey($deleted->id)->exists());
    }

    public function test_opening_and_saving_an_ad_ask_to_edit_ads_and_write_nothing(): void
    {
        $ad = Ad::factory()->create();
        $this->refuse(Ability::AdsEdit);

        Livewire::test(AdForm::class, ['campaignId' => $ad->campaign_id])->call('editAd')->assertForbidden();
        Livewire::test(AdForm::class, ['campaignId' => $ad->campaign_id])->call('editAd', $ad->id)->assertForbidden();
        Livewire::test(AdForm::class, ['campaignId' => $ad->campaign_id])
            ->set('adName', 'Bannière')
            ->set('adConditions', [['param' => 'utm_content', 'value' => 'banniere']])
            ->call('saveAd')
            ->assertForbidden();

        $this->assertSame(1, Ad::query()->count());
    }

    public function test_deleting_an_ad_asks_to_delete_it_and_deletes_nothing(): void
    {
        $ad = Ad::factory()->create();
        $this->refuse(Ability::AdsDelete);

        $page = Livewire::test(CampaignDetailPage::class, ['campaign' => $ad->campaign]);
        $page->call('confirmDeleteAd', $ad->id)->assertForbidden();
        Livewire::test(CampaignDetailPage::class, ['campaign' => $ad->campaign])->set('deleteAdId', $ad->id)->call('deleteAdConfirmed')->assertForbidden();

        $this->assertTrue(Ad::query()->whereKey($ad->id)->exists());
    }

    public function test_erasing_a_visitor_asks_to_delete_visitors_and_erases_nothing(): void
    {
        $visitor = Visitor::factory()->create();
        $this->refuse(Ability::VisitorsDelete);

        Livewire::test(VisitorDetailPage::class, ['visitor' => $visitor])->call('forget')->assertForbidden();

        $this->assertTrue(Visitor::query()->whereKey($visitor->id)->exists());
    }

    public function test_every_search_console_gesture_asks_to_manage_the_integration_and_changes_nothing(): void
    {
        $before = SearchConsoleConnection::factory()->create()->fresh()?->toArray();
        $this->refuse(Ability::IntegrationsManage);

        foreach (['reloadProperties', 'syncNow', 'disconnectConfirmed'] as $gesture) {
            Livewire::test(IntegrationsPage::class)->call($gesture)->assertForbidden();
        }

        Livewire::test(IntegrationsPage::class)->call('selectProperty', 'sc-domain:example.com')->assertForbidden();

        $this->assertSame($before, SearchConsoleConnection::current()?->toArray());
    }

    private function refuse(Ability $ability): void
    {
        Gate::define($ability, fn (TestAdmin $admin): bool => false);
    }
}
