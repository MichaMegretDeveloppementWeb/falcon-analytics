<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\Dashboard;

use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;

/**
 * The retroactive attribution rule: match a visit's marketing params against the
 * campaign/ad URL conditions, the most specific (most conditions) definition wins.
 * Pure and deterministic, no persistence, so it lives in the calculation layer
 * rather than the repository.
 */
final class AttributionResolver
{
    /**
     * The most specific candidate whose conditions all match the params, or null.
     * Specificity is the number of conditions; on a tie the first seen wins.
     *
     * @param  list<Campaign>|list<Ad>  $candidates
     * @param  array<string, string>  $params
     */
    public function mostSpecific(array $candidates, array $params): Campaign|Ad|null
    {
        $best = null;
        $bestSpecificity = 0;

        foreach ($candidates as $candidate) {
            $conditions = $candidate->match_conditions ?? [];

            if ($conditions === [] || ! $this->matches($conditions, $params)) {
                continue;
            }

            if (count($conditions) > $bestSpecificity) {
                $best = $candidate;
                $bestSpecificity = count($conditions);
            }
        }

        return $best;
    }

    /**
     * Whether every condition is present in the params with the exact value.
     *
     * @param  array<int, array{param: string, value: string}>  $conditions
     * @param  array<string, string>  $params
     */
    public function matches(array $conditions, array $params): bool
    {
        foreach ($conditions as $condition) {
            if (($params[$condition['param']] ?? null) !== $condition['value']) {
                return false;
            }
        }

        return true;
    }
}
