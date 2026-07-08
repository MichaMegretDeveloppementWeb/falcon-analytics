<?php

declare(strict_types=1);

namespace Falcon\Analytics\Actions;

use Falcon\Analytics\Models\Campaign;

/**
 * Create or update a marketing campaign. Keeps the write off the Livewire layer so
 * the component only gathers input and calls execute().
 */
final readonly class SaveCampaignAction
{
    /**
     * @param  list<array{param: string, value: string}>  $conditions
     */
    public function execute(?int $campaignId, string $name, ?string $platform, array $conditions): Campaign
    {
        $campaign = $campaignId !== null ? Campaign::findOrFail($campaignId) : new Campaign;

        $campaign->fill([
            'name' => $name,
            'platform' => $platform,
            'match_conditions' => $conditions,
        ])->save();

        return $campaign;
    }
}
