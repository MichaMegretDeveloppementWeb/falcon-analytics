<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\MetricDelta;

/**
 * Pure calculator for the overview audience metrics derived from the raw
 * new/returning counts. Deterministic, no I/O.
 */
final class OverviewMetricsCalculator
{
    /**
     * Share of new visitors among the period's visitors, period over period.
     *
     * @param  array{new: int, returning: int}  $current
     * @param  array{new: int, returning: int}  $previous
     */
    public function newVisitorRate(array $current, array $previous): MetricDelta
    {
        return new MetricDelta($this->rate($current), $this->rate($previous));
    }

    /**
     * @param  array{new: int, returning: int}  $counts
     */
    private function rate(array $counts): float
    {
        $total = $counts['new'] + $counts['returning'];

        return $total > 0 ? $counts['new'] / $total * 100 : 0.0;
    }
}
