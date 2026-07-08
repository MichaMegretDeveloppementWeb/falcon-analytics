<?php

declare(strict_types=1);

namespace Falcon\Analytics\Actions;

use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\AdObjective;
use Illuminate\Support\Facades\DB;

/**
 * Create or update an ad together with its conversion objectives. The ad row and the
 * objective rebuild happen in one transaction, so a failure never leaves the ad saved
 * with stale (or missing) objectives.
 */
final readonly class SaveAdAction
{
    /**
     * @param  list<array{param: string, value: string}>  $conditions
     * @param  list<array{type: string, reference: string, label: string, value: string|null}>  $objectives
     */
    public function execute(?int $adId, int $campaignId, string $name, array $conditions, array $objectives): Ad
    {
        return DB::transaction(function () use ($adId, $campaignId, $name, $conditions, $objectives): Ad {
            $ad = $adId !== null
                ? Ad::findOrFail($adId)
                : new Ad(['campaign_id' => $campaignId]);

            $ad->fill([
                'name' => $name,
                'match_conditions' => $conditions,
            ])->save();

            AdObjective::query()->where('ad_id', $ad->id)->delete();

            foreach ($objectives as $objective) {
                AdObjective::create([
                    'ad_id' => $ad->id,
                    'type' => $objective['type'],
                    'reference' => $objective['reference'],
                    'value' => $objective['type'] === 'event'
                        ? (is_numeric($objective['value']) ? $objective['value'] : 0)
                        : null,
                ]);
            }

            return $ad;
        });
    }
}
