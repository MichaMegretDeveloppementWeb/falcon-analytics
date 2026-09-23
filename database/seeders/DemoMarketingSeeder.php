<?php

declare(strict_types=1);

namespace Falcon\Analytics\Database\Seeders;

use Falcon\Analytics\Actions\SaveAdAction;
use Falcon\Analytics\Actions\SaveCampaignAction;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Campaign;
use Illuminate\Database\Seeder;

/**
 * Two demonstration campaigns and their ads, laid through the actions the
 * screens use · each is laid once, however many passes run.
 */
final class DemoMarketingSeeder extends Seeder
{
    /**
     * Each campaign by the `utm_campaign` of its links, and its ads by their
     * `utm_content` · the traffic builds its campaign links from these.
     */
    public const CAMPAIGNS = [
        'demo-printemps' => [
            'name' => 'Printemps (démonstration)',
            'platform' => 'Meta',
            'link' => 'utm_source=meta&utm_medium=paid_social',
            'ads' => ['visuel-a' => 'Visuel A', 'visuel-b' => 'Visuel B'],
        ],
        'demo-recherche' => [
            'name' => 'Recherche (démonstration)',
            'platform' => 'Google',
            'link' => 'utm_source=google&utm_medium=cpc',
            'ads' => ['annonce-texte' => 'Annonce texte'],
        ],
    ];

    public function __construct(
        private readonly SaveCampaignAction $campaigns,
        private readonly SaveAdAction $ads,
        private readonly EventRegistry $events,
        private readonly FunnelRegistry $funnels,
    ) {}

    /**
     * @return array<string, int>
     */
    public function run(): array
    {
        $laid = ['campagne|campagnes' => 0, 'pub|pubs' => 0];
        $objectives = $this->objectives();

        foreach (self::CAMPAIGNS as $campaignLink => $campaign) {
            if (Campaign::query()->where('name', $campaign['name'])->exists()) {
                continue;
            }

            $saved = $this->campaigns->execute(null, $campaign['name'], $campaign['platform'], [['param' => 'utm_campaign', 'value' => $campaignLink]]);
            $laid['campagne|campagnes']++;

            foreach ($campaign['ads'] as $adLink => $name) {
                $this->ads->execute(null, $saved->id, $name, [['param' => 'utm_content', 'value' => $adLink]], $objectives);
                $laid['pub|pubs']++;
            }
        }

        return $laid;
    }

    /**
     * The host's first conversion and first funnel, when it declares them.
     *
     * @return list<array{type: string, reference: string, label: string}>
     */
    private function objectives(): array
    {
        $objectives = [];

        foreach ($this->events->all() as $event) {
            if ($event->isConversion()) {
                $objectives[] = ['type' => 'event', 'reference' => $event->name, 'label' => $event->label];

                break;
            }
        }

        foreach ($this->funnels->all() as $funnel) {
            $objectives[] = ['type' => 'funnel', 'reference' => $funnel->key, 'label' => $funnel->label];

            break;
        }

        return $objectives;
    }
}
