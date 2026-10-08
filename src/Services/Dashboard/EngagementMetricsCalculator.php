<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\MetricDelta;
use Falcon\Analytics\DTOs\Dashboard\Period;

/**
 * Pure calculator for the engagement headline shared by the overview and
 * sessions screens: turns the repository's raw counts and daily rows into
 * deltas, ratios and zero-filled sparkline series. Deterministic, no I/O.
 *
 * @internal
 */
final class EngagementMetricsCalculator
{
    /**
     * The visitors of a period reaching erased days are null, and stay so.
     *
     * @param  array{visitors: int|null, sessions: int, pageviews: int, avgSeconds: float, bounces: int}  $current
     * @param  array{visitors: int|null, sessions: int, pageviews: int, avgSeconds: float, bounces: int}  $previous
     * @return array{visitors: MetricDelta, sessions: MetricDelta, pageviews: MetricDelta, avgSeconds: MetricDelta, pagesPerSession: MetricDelta, bounceRate: MetricDelta}
     */
    public function headline(array $current, array $previous): array
    {
        return [
            'visitors' => new MetricDelta($this->known($current['visitors']), $this->known($previous['visitors'])),
            'sessions' => new MetricDelta((float) $current['sessions'], (float) $previous['sessions']),
            'pageviews' => new MetricDelta((float) $current['pageviews'], (float) $previous['pageviews']),
            'avgSeconds' => new MetricDelta($current['avgSeconds'], $previous['avgSeconds']),
            'pagesPerSession' => new MetricDelta(
                $this->perSession($current['pageviews'], $current['sessions']),
                $this->perSession($previous['pageviews'], $previous['sessions']),
            ),
            'bounceRate' => new MetricDelta(
                $this->bounceRate($current['bounces'], $current['sessions']),
                $this->bounceRate($previous['bounces'], $previous['sessions']),
            ),
        ];
    }

    /**
     * @param  array{visitors: int|null, sessions: int, pageviews: int, avgSeconds: float, bounces: int}  $today
     * @param  array{visitors: int|null, sessions: int, pageviews: int, avgSeconds: float, bounces: int}  $yesterday
     * @return array<string, array{today: float|null, yesterday: float|null}>
     */
    public function spotlight(array $today, array $yesterday): array
    {
        return [
            'visitors' => ['today' => $this->known($today['visitors']), 'yesterday' => $this->known($yesterday['visitors'])],
            'sessions' => ['today' => (float) $today['sessions'], 'yesterday' => (float) $yesterday['sessions']],
            'avgSeconds' => ['today' => $today['avgSeconds'], 'yesterday' => $yesterday['avgSeconds']],
            'bounceRate' => [
                'today' => $this->bounceRate($today['bounces'], $today['sessions']),
                'yesterday' => $this->bounceRate($yesterday['bounces'], $yesterday['sessions']),
            ],
        ];
    }

    /**
     * A day read from its totals has no visitors · the visitors' line is then
     * left empty rather than drawn with a hole.
     *
     * @param  array<string, array{sessions: int, visitors: int|null, pageviews: int, avgSeconds: float, bounces: int}>  $rows  keyed by 'Y-m-d'
     * @return array{visitors: list<float>, sessions: list<float>, avgSeconds: list<float>, bounceRate: list<float>, pagesPerSession: list<float>}
     */
    public function sparklines(array $rows, Period $period): array
    {
        $series = ['visitors' => [], 'sessions' => [], 'avgSeconds' => [], 'bounceRate' => [], 'pagesPerSession' => []];
        $visitorsKnown = true;

        foreach ($period->eachDay() as $cursor) {
            $row = $rows[$cursor->format('Y-m-d')] ?? ['sessions' => 0, 'visitors' => 0, 'pageviews' => 0, 'avgSeconds' => 0.0, 'bounces' => 0];
            $sessions = $row['sessions'];

            $visitorsKnown = $visitorsKnown && $row['visitors'] !== null;
            $series['visitors'][] = (float) $row['visitors'];
            $series['sessions'][] = (float) $sessions;
            $series['avgSeconds'][] = $row['avgSeconds'];
            $series['bounceRate'][] = $this->bounceRate($row['bounces'], $sessions);
            $series['pagesPerSession'][] = $this->perSession($row['pageviews'], $sessions);
        }

        if (! $visitorsKnown) {
            $series['visitors'] = [];
        }

        return $series;
    }

    private function known(?int $count): ?float
    {
        return $count === null ? null : (float) $count;
    }

    private function perSession(int $pageviews, int $sessions): float
    {
        return $sessions > 0 ? $pageviews / $sessions : 0.0;
    }

    private function bounceRate(int $bounces, int $sessions): float
    {
        return $sessions > 0 ? $bounces / $sessions * 100 : 0.0;
    }
}
