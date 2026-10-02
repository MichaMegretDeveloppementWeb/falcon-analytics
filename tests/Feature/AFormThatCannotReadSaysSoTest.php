<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Livewire\Admin\AdForm;
use Falcon\Analytics\Livewire\Admin\CampaignForm;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/**
 * Opening a campaign or an ad tells two failures apart: one that no longer
 * exists is « introuvable », one the database did not give back could not be
 * loaded, and trying again may work.
 */
final class AFormThatCannotReadSaysSoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(TestAdmin::create([]), 'admin');
    }

    public function test_a_campaign_that_no_longer_exists_is_not_found(): void
    {
        Livewire::test(CampaignForm::class)
            ->call('editCampaign', 999)
            ->assertReturned(static fn (mixed $opened): bool => $opened === false)
            ->assertDispatched('ui-toast', type: 'danger', title: 'Cette campagne est introuvable. Actualisez la page.');
    }

    public function test_a_campaign_the_database_did_not_give_back_could_not_be_loaded(): void
    {
        $campaign = Campaign::factory()->create();
        $form = Livewire::test(CampaignForm::class);

        $this->withoutTable('falcon_analytics_campaigns', function () use ($form, $campaign): void {
            $form->call('editCampaign', $campaign->id)
                ->assertReturned(static fn (mixed $opened): bool => $opened === false)
                ->assertDispatched('ui-toast', type: 'danger', title: 'Impossible de charger cette campagne. Réessayez.')
                ->assertNotDispatched('ui-toast', type: 'danger', title: 'Cette campagne est introuvable. Actualisez la page.');
        });
    }

    public function test_an_ad_that_no_longer_exists_is_not_found(): void
    {
        $campaign = Campaign::factory()->create();

        Livewire::test(AdForm::class, ['campaignId' => $campaign->id])
            ->call('editAd', 999)
            ->assertReturned(static fn (mixed $opened): bool => $opened === false)
            ->assertDispatched('ui-toast', type: 'danger', title: 'Cette publicité est introuvable. Actualisez la page.');
    }

    public function test_an_ad_the_database_did_not_give_back_could_not_be_loaded(): void
    {
        $ad = Ad::factory()->create();
        $form = Livewire::test(AdForm::class, ['campaignId' => $ad->campaign_id]);

        $this->withoutTable('falcon_analytics_ads', function () use ($form, $ad): void {
            $form->call('editAd', $ad->id)
                ->assertReturned(static fn (mixed $opened): bool => $opened === false)
                ->assertDispatched('ui-toast', type: 'danger', title: 'Impossible de charger cette publicité. Réessayez.')
                ->assertNotDispatched('ui-toast', type: 'danger', title: 'Cette publicité est introuvable. Actualisez la page.');
        });
    }
}
