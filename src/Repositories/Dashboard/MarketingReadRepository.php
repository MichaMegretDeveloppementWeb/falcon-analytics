<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories\Dashboard;

use Falcon\Analytics\Models\Ad;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read model for the marketing screens. For now it resolves a session's raw
 * (campaign, ad) values to a defined ad; the per-ad performance report is built
 * on top of this in a later step.
 */
final class MarketingReadRepository
{
    /**
     * The ad a session's raw campaign/ad values map to, or null when the pair is
     * not defined. The (campaign key, ad key) pair is unique, so an ad key reused
     * across campaigns never collides — the campaign disambiguates it.
     */
    public function resolveAd(?string $campaignKey, ?string $adKey): ?Ad
    {
        if ($campaignKey === null || $adKey === null) {
            return null;
        }

        return Ad::query()
            ->where('key', $adKey)
            ->whereHas('campaign', fn (Builder $query): Builder => $query->where('key', $campaignKey))
            ->with('campaign')
            ->first();
    }
}
