<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories\Dashboard;

use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;

/**
 * Read model for the marketing screens. Resolves a session's captured landing
 * parameters to a defined ad or campaign by matching their conditions (all must
 * hold, AND); on overlap the most specific rule (most conditions) wins, so a
 * broad campaign rule never steals a session from a precise ad rule.
 */
final class MarketingReadRepository
{
    /**
     * @param  array<string, string>|null  $params
     */
    public function resolveAd(?array $params): ?Ad
    {
        if ($params === null || $params === []) {
            return null;
        }

        $best = null;
        $bestSpecificity = 0;

        foreach (Ad::query()->where('is_active', true)->with('campaign')->get() as $ad) {
            $conditions = $ad->match_conditions ?? [];

            if ($conditions === [] || ! $this->matches($conditions, $params)) {
                continue;
            }

            if (count($conditions) > $bestSpecificity) {
                $best = $ad;
                $bestSpecificity = count($conditions);
            }
        }

        return $best;
    }

    /**
     * @param  array<string, string>|null  $params
     */
    public function resolveCampaign(?array $params): ?Campaign
    {
        if ($params === null || $params === []) {
            return null;
        }

        $best = null;
        $bestSpecificity = 0;

        foreach (Campaign::query()->where('is_active', true)->get() as $campaign) {
            $conditions = $campaign->match_conditions ?? [];

            if ($conditions === [] || ! $this->matches($conditions, $params)) {
                continue;
            }

            if (count($conditions) > $bestSpecificity) {
                $best = $campaign;
                $bestSpecificity = count($conditions);
            }
        }

        return $best;
    }

    /**
     * @param  array<int, array{param: string, value: string}>  $conditions
     * @param  array<string, string>  $params
     */
    private function matches(array $conditions, array $params): bool
    {
        foreach ($conditions as $condition) {
            if (($params[$condition['param']] ?? null) !== $condition['value']) {
                return false;
            }
        }

        return true;
    }
}
