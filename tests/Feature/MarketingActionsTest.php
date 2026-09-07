<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Actions\DeleteAdAction;
use Falcon\Analytics\Actions\DeleteCampaignAction;
use Falcon\Analytics\Actions\SaveAdAction;
use Falcon\Analytics\Actions\SaveCampaignAction;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\AdObjective;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class MarketingActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_then_updates_a_campaign(): void
    {
        $campaign = (new SaveCampaignAction)->execute(null, 'Été', 'Meta', [['param' => 'src', 'value' => 'meta']]);

        $this->assertSame('Été', $campaign->name);
        $this->assertSame('Meta', $campaign->platform);
        $this->assertSame(1, Campaign::query()->count());

        $updated = (new SaveCampaignAction)->execute($campaign->id, 'Été 2026', null, [['param' => 'src', 'value' => 'x']]);

        $this->assertSame($campaign->id, $updated->id);
        $this->assertSame('Été 2026', $updated->name);
        $this->assertNull($updated->platform);
        $this->assertSame(1, Campaign::query()->count());
    }

    public function test_it_saves_an_ad_and_rebuilds_its_objectives_in_one_transaction(): void
    {
        $campaign = Campaign::create(['name' => 'C', 'match_conditions' => [['param' => 'src', 'value' => 'meta']]]);

        $ad = (new SaveAdAction)->execute(null, $campaign->id, 'Cabrio', [['param' => 'creative', 'value' => 'cabrio']], [
            ['type' => 'event', 'reference' => 'Lead', 'label' => 'Lead'],
            ['type' => 'funnel', 'reference' => 'concours', 'label' => 'Concours'],
        ]);

        $this->assertSame('Cabrio', $ad->name);
        $this->assertSame($campaign->id, $ad->campaign_id);
        $this->assertSame(2, AdObjective::query()->where('ad_id', $ad->id)->count());

        // Réenregistrer remplace le jeu d'objectifs plutôt que de s'y ajouter.
        (new SaveAdAction)->execute($ad->id, $campaign->id, 'Cabrio', [['param' => 'creative', 'value' => 'cabrio']], [
            ['type' => 'event', 'reference' => 'Lead', 'label' => 'Lead'],
        ]);

        $this->assertSame(1, AdObjective::query()->where('ad_id', $ad->id)->count());
    }

    public function test_it_deletes_a_campaign_with_its_ads_and_objectives(): void
    {
        $campaign = Campaign::create(['name' => 'C', 'match_conditions' => [['param' => 'src', 'value' => 'meta']]]);
        $ad = Ad::create(['campaign_id' => $campaign->id, 'name' => 'A', 'match_conditions' => [['param' => 'x', 'value' => 'y']]]);
        AdObjective::create(['ad_id' => $ad->id, 'type' => 'event', 'reference' => 'Lead']);

        (new DeleteCampaignAction)->execute($campaign->id);

        $this->assertSame(0, Campaign::query()->count());
        $this->assertSame(0, Ad::query()->count());
        $this->assertSame(0, AdObjective::query()->count());
    }

    public function test_it_deletes_an_ad_only_when_it_belongs_to_the_given_campaign(): void
    {
        $campaign = Campaign::create(['name' => 'C', 'match_conditions' => [['param' => 'src', 'value' => 'meta']]]);
        $ad = Ad::create(['campaign_id' => $campaign->id, 'name' => 'A', 'match_conditions' => [['param' => 'x', 'value' => 'y']]]);
        AdObjective::create(['ad_id' => $ad->id, 'type' => 'event', 'reference' => 'Lead']);

        // Mauvais identifiant de campagne : la publicité et ses objectifs restent intacts.
        (new DeleteAdAction)->execute($ad->id, $campaign->id + 999);

        $this->assertSame(1, Ad::query()->count());
        $this->assertSame(1, AdObjective::query()->count());

        (new DeleteAdAction)->execute($ad->id, $campaign->id);

        $this->assertSame(0, Ad::query()->count());
        $this->assertSame(0, AdObjective::query()->count());
    }
}
