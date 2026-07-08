<?php

declare(strict_types=1);

namespace Falcon\Analytics\Actions;

use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\AdObjective;
use Illuminate\Support\Facades\DB;

/**
 * Delete an ad and its conversion objectives, scoped to its campaign so an id from
 * another campaign cannot be removed. Explicit deletes inside a transaction, matching
 * the campaign deletion. The captured traffic is left untouched.
 */
final readonly class DeleteAdAction
{
    public function execute(int $adId, int $campaignId): void
    {
        DB::transaction(function () use ($adId, $campaignId): void {
            // Confirm the ad belongs to the campaign before touching anything, so an
            // id from another campaign leaves both the ad and its objectives intact.
            if (! Ad::query()->whereKey($adId)->where('campaign_id', $campaignId)->exists()) {
                return;
            }

            AdObjective::query()->where('ad_id', $adId)->delete();
            Ad::query()->whereKey($adId)->delete();
        });
    }
}
