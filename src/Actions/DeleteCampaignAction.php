<?php

declare(strict_types=1);

namespace Falcon\Analytics\Actions;

use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\AdObjective;
use Falcon\Analytics\Models\Campaign;
use Illuminate\Support\Facades\DB;

/**
 * Delete a campaign and everything it owns (its ads and their objectives). Deletes
 * explicitly inside a transaction rather than relying on the FK cascade, so it
 * behaves identically on every driver. The captured traffic is left untouched.
 */
final readonly class DeleteCampaignAction
{
    public function execute(int $campaignId): void
    {
        DB::transaction(function () use ($campaignId): void {
            $adIds = Ad::query()->where('campaign_id', $campaignId)->pluck('id');
            AdObjective::query()->whereIn('ad_id', $adIds)->delete();
            Ad::query()->where('campaign_id', $campaignId)->delete();
            Campaign::query()->whereKey($campaignId)->delete();
        });
    }
}
