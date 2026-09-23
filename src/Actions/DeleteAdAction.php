<?php

declare(strict_types=1);

namespace Falcon\Analytics\Actions;

use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\AdObjective;
use Illuminate\Support\Facades\DB;

/**
 * Delete an ad and its conversion objectives, scoped to its campaign so an id from
 * another campaign cannot be removed. Deletes explicitly inside a transaction, so it
 * behaves the same on every driver. The captured traffic is left untouched.
 *
 * @internal
 */
final readonly class DeleteAdAction
{
    public function execute(int $adId, int $campaignId): void
    {
        DB::transaction(function () use ($adId, $campaignId): void {
            if (! Ad::query()->whereKey($adId)->where('campaign_id', $campaignId)->exists()) {
                return;
            }

            AdObjective::query()->where('ad_id', $adId)->delete();
            Ad::query()->whereKey($adId)->delete();
        });
    }
}
