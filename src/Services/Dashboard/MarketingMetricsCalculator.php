<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Support\DateLabel;
use Falcon\Analytics\Support\NumberLabel;

/**
 * Pure marketing metric maths, kept out of the Livewire widgets: the conversion
 * rate and the zero-filled daily trend (sessions, conversions and per-day rate).
 * Mirrors the overview's EngagementMetricsCalculator / TrendSeriesCalculator split.
 *
 * @internal
 */
final class MarketingMetricsCalculator
{
    /**
     * Conversion rate (%) of converting visitors over reached visitors.
     */
    public function rate(float $conversions, float $visitors): float
    {
        return $visitors > 0 ? $conversions / $visitors * 100 : 0.0;
    }

    public function rateLabel(float $rate): string
    {
        return NumberLabel::percent($rate, 1);
    }

    /**
     * The daily trend for the period: one entry per day (zero-filled) for the labels,
     * sessions, visitors, conversions and the per-day conversion rate.
     *
     * @param  array<string, int>  $dailySessions  day (Y-m-d) => sessions
     * @param  array<string, int>  $dailyConversions  day (Y-m-d) => conversions
     * @param  array<string, int>  $dailyVisitors  day (Y-m-d) => distinct visitors
     * @return array{labels: list<string>, sessions: list<int>, visitors: list<int>, conversions: list<int>, rates: list<float>}
     */
    public function trend(Period $period, array $dailySessions, array $dailyConversions, array $dailyVisitors): array
    {
        $labels = [];
        $sessions = [];
        $visitors = [];
        $conversions = [];
        $rates = [];

        foreach ($period->eachDay() as $day) {
            $key = $day->toDateString();
            $daySessions = $dailySessions[$key] ?? 0;
            $dayConversions = $dailyConversions[$key] ?? 0;

            $labels[] = DateLabel::for($day, 'j M');
            $sessions[] = $daySessions;
            $visitors[] = $dailyVisitors[$key] ?? 0;
            $conversions[] = $dayConversions;
            $rates[] = $daySessions > 0 ? round($dayConversions / $daySessions * 100, 1) : 0.0;
        }

        return ['labels' => $labels, 'sessions' => $sessions, 'visitors' => $visitors, 'conversions' => $conversions, 'rates' => $rates];
    }
}
