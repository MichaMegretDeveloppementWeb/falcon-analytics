<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\DTOs\Dashboard\TrendPoint;

/**
 * Pure calculator that turns the repository's raw daily rows into a zero-filled
 * list of trend points, so the traffic chart draws a continuous line.
 */
final class TrendSeriesCalculator
{
    /**
     * @param  array<string, array{sessions: int, pageviews: int}>  $rows  keyed by 'Y-m-d'
     * @return list<TrendPoint>
     */
    public function points(array $rows, Period $period): array
    {
        $points = [];

        for ($cursor = $period->from->startOfDay(); $cursor->lessThanOrEqualTo($period->to); $cursor = $cursor->addDay()) {
            $row = $rows[$cursor->format('Y-m-d')] ?? ['sessions' => 0, 'pageviews' => 0];

            $points[] = new TrendPoint(
                date: $cursor,
                sessions: $row['sessions'],
                pageviews: $row['pageviews'],
            );
        }

        return $points;
    }
}
