<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\MetricDelta;
use Falcon\Analytics\DTOs\Dashboard\MetricTrend;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\DTOs\Dashboard\VisitorMetrics;

/**
 * Pure calculator for the visitors screen headline tiles: turns the repository's
 * raw counts and daily rows into deltas, ratios and zero-filled sparkline
 * series. Deterministic, no persistence, no side effects.
 */
final class VisitorMetricsCalculator
{
    /**
     * @param  array{visitors: int, new: int, sessions: int}  $current
     * @param  array{visitors: int, new: int, sessions: int}  $previous
     * @param  array{active: array<string, array{sessions: int, visitors: int}>, new: array<string, int>}  $daily
     */
    public function compute(array $current, array $previous, array $daily, Period $period): VisitorMetrics
    {
        $series = $this->series($daily, $period);

        return new VisitorMetrics(
            visitors: new MetricTrend(
                new MetricDelta((float) $current['visitors'], (float) $previous['visitors']),
                $series['visitors'],
            ),
            newVisitors: new MetricTrend(
                new MetricDelta((float) $current['new'], (float) $previous['new']),
                $series['new'],
            ),
            returning: new MetricTrend(
                new MetricDelta(
                    (float) max(0, $current['visitors'] - $current['new']),
                    (float) max(0, $previous['visitors'] - $previous['new']),
                ),
                $series['returning'],
            ),
            sessionsPerVisitor: new MetricTrend(
                new MetricDelta(
                    $this->ratio($current['sessions'], $current['visitors']),
                    $this->ratio($previous['sessions'], $previous['visitors']),
                ),
                $series['sessionsPerVisitor'],
            ),
        );
    }

    /**
     * @param  array{active: array<string, array{sessions: int, visitors: int}>, new: array<string, int>}  $daily
     * @return array{visitors: list<float>, new: list<float>, returning: list<float>, sessionsPerVisitor: list<float>}
     */
    private function series(array $daily, Period $period): array
    {
        $series = ['visitors' => [], 'new' => [], 'returning' => [], 'sessionsPerVisitor' => []];

        for ($cursor = $period->from->startOfDay(); $cursor->lessThanOrEqualTo($period->to); $cursor = $cursor->addDay()) {
            $key = $cursor->format('Y-m-d');
            $active = $daily['active'][$key] ?? ['sessions' => 0, 'visitors' => 0];
            $visitors = $active['visitors'];
            $new = min($daily['new'][$key] ?? 0, $visitors);

            $series['visitors'][] = (float) $visitors;
            $series['new'][] = (float) $new;
            $series['returning'][] = (float) max(0, $visitors - $new);
            $series['sessionsPerVisitor'][] = $this->ratio($active['sessions'], $visitors);
        }

        return $series;
    }

    private function ratio(int $numerator, int $denominator): float
    {
        return $denominator > 0 ? $numerator / $denominator : 0.0;
    }
}
