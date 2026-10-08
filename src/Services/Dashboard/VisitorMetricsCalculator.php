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
 *
 * @internal
 */
final class VisitorMetricsCalculator
{
    /**
     * A period reaching days whose sessions are erased has no counts · its
     * figures are then null, and its daily lines empty.
     *
     * @param  array{visitors: int, new: int, sessions: int}|null  $current
     * @param  array{visitors: int, new: int, sessions: int}|null  $previous
     * @param  array{active: array<string, array{sessions: int, visitors: int}>, new: array<string, int>}|null  $daily
     */
    public function compute(?array $current, ?array $previous, ?array $daily, Period $period): VisitorMetrics
    {
        $series = $daily === null
            ? ['visitors' => [], 'new' => [], 'returning' => [], 'sessionsPerVisitor' => []]
            : $this->series($daily, $period);

        return new VisitorMetrics(
            visitors: new MetricTrend(
                new MetricDelta($this->visitors($current), $this->visitors($previous)),
                $series['visitors'],
            ),
            newVisitors: new MetricTrend(
                new MetricDelta($this->newVisitors($current), $this->newVisitors($previous)),
                $series['new'],
            ),
            returning: new MetricTrend(
                new MetricDelta($this->returning($current), $this->returning($previous)),
                $series['returning'],
            ),
            sessionsPerVisitor: new MetricTrend(
                new MetricDelta($this->sessionsPerVisitor($current), $this->sessionsPerVisitor($previous)),
                $series['sessionsPerVisitor'],
            ),
        );
    }

    /** @param  array{visitors: int, new: int, sessions: int}|null  $counts */
    private function visitors(?array $counts): ?float
    {
        return $counts === null ? null : (float) $counts['visitors'];
    }

    /** @param  array{visitors: int, new: int, sessions: int}|null  $counts */
    private function newVisitors(?array $counts): ?float
    {
        return $counts === null ? null : (float) $counts['new'];
    }

    /** @param  array{visitors: int, new: int, sessions: int}|null  $counts */
    private function returning(?array $counts): ?float
    {
        return $counts === null ? null : (float) max(0, $counts['visitors'] - $counts['new']);
    }

    /** @param  array{visitors: int, new: int, sessions: int}|null  $counts */
    private function sessionsPerVisitor(?array $counts): ?float
    {
        return $counts === null ? null : $this->ratio($counts['sessions'], $counts['visitors']);
    }

    /**
     * @param  array{active: array<string, array{sessions: int, visitors: int}>, new: array<string, int>}  $daily
     * @return array{visitors: list<float>, new: list<float>, returning: list<float>, sessionsPerVisitor: list<float>}
     */
    private function series(array $daily, Period $period): array
    {
        $series = ['visitors' => [], 'new' => [], 'returning' => [], 'sessionsPerVisitor' => []];

        foreach ($period->eachDay() as $cursor) {
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
