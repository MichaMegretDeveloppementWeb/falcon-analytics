<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\Period;

/**
 * Pure marketing metric maths, kept out of the Livewire widgets: the conversion
 * rate and the zero-filled daily trend (sessions, conversions and per-day rate).
 * Mirrors the overview's EngagementMetricsCalculator / TrendSeriesCalculator split.
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
        return number_format($rate, 1, ',', ' ')."\u{00A0}%";
    }

    /**
     * The daily trend for the period: one entry per day (zero-filled) for the labels,
     * sessions, conversions and the per-day conversion rate.
     *
     * @param  array<string, int>  $dailySessions  day (Y-m-d) => sessions
     * @param  array<string, int>  $dailyConversions  day (Y-m-d) => conversions
     * @return array{labels: list<string>, sessions: list<int>, conversions: list<int>, rates: list<float>}
     */
    public function trend(Period $period, array $dailySessions, array $dailyConversions): array
    {
        $labels = [];
        $sessions = [];
        $conversions = [];
        $rates = [];

        foreach ($period->eachDay() as $day) {
            $key = $day->toDateString();
            $daySessions = $dailySessions[$key] ?? 0;
            $dayConversions = $dailyConversions[$key] ?? 0;

            $labels[] = $day->isoFormat('D MMM');
            $sessions[] = $daySessions;
            $conversions[] = $dayConversions;
            $rates[] = $daySessions > 0 ? round($dayConversions / $daySessions * 100, 1) : 0.0;
        }

        return ['labels' => $labels, 'sessions' => $sessions, 'conversions' => $conversions, 'rates' => $rates];
    }
}
