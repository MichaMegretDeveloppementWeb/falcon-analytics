<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\MetricDelta;
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

    /**
     * The four headline figures of a marketing screen against the previous
     * period · sessions, visitors, conversions and their rate. Visitors and
     * conversions count people, unknown over days whose rows are erased, and
     * the rate with them.
     *
     * @param  array{sessions: int, visitors: int|null}  $traffic
     * @param  array{sessions: int, visitors: int|null}  $trafficBefore
     * @return array{sessionsDelta: MetricDelta, visitorsDelta: MetricDelta, conversionsDelta: MetricDelta, rate: float|null, rateDelta: MetricDelta}
     */
    public function headline(array $traffic, array $trafficBefore, ?int $conversions, ?int $conversionsBefore): array
    {
        $rate = $this->rateOf($conversions, $traffic['visitors']);

        return [
            'sessionsDelta' => new MetricDelta((float) $traffic['sessions'], (float) $trafficBefore['sessions']),
            'visitorsDelta' => new MetricDelta($this->known($traffic['visitors']), $this->known($trafficBefore['visitors'])),
            'conversionsDelta' => new MetricDelta($this->known($conversions), $this->known($conversionsBefore)),
            'rate' => $rate,
            'rateDelta' => new MetricDelta($rate, $this->rateOf($conversionsBefore, $trafficBefore['visitors'])),
        ];
    }

    /** The rate, or null when the conversions or the visitors are unknown. */
    public function rateOf(?int $conversions, ?int $visitors): ?float
    {
        return $conversions === null || $visitors === null ? null : $this->rate((float) $conversions, (float) $visitors);
    }

    private function known(?int $count): ?float
    {
        return $count === null ? null : (float) $count;
    }

    public function rateLabel(float $rate): string
    {
        return NumberLabel::percent($rate, 1);
    }

    /**
     * The daily trend for the period: one entry per day (zero-filled) for the labels,
     * sessions, visitors, conversions and the per-day conversion rate · the
     * lines of what is unknown left empty rather than drawn as zeros.
     *
     * A day's rate divides people by people, like the headline rate · its
     * converting visitors over its visitors, never over its sessions.
     *
     * @param  array<string, int>  $dailySessions  day (Y-m-d) => sessions
     * @param  array<string, int>|null  $dailyConversions  day (Y-m-d) => conversions
     * @param  array<string, int>|null  $dailyVisitors  day (Y-m-d) => distinct visitors
     * @return array{labels: list<string>, sessions: list<int>, visitors: list<int>, conversions: list<int>, rates: list<float>}
     */
    public function trend(Period $period, array $dailySessions, ?array $dailyConversions, ?array $dailyVisitors): array
    {
        $labels = [];
        $sessions = [];
        $visitors = [];
        $conversions = [];
        $rates = [];

        foreach ($period->eachDay() as $day) {
            $key = $day->toDateString();
            $dayConversions = $dailyConversions[$key] ?? 0;
            $dayVisitors = $dailyVisitors[$key] ?? 0;

            $labels[] = DateLabel::for($day, 'j M');
            $sessions[] = $dailySessions[$key] ?? 0;
            $visitors[] = $dayVisitors;
            $conversions[] = $dayConversions;
            $rates[] = round($this->rate((float) $dayConversions, (float) $dayVisitors), 1);
        }

        return [
            'labels' => $labels,
            'sessions' => $sessions,
            'visitors' => $dailyVisitors === null ? [] : $visitors,
            'conversions' => $dailyConversions === null ? [] : $conversions,
            'rates' => $dailyConversions === null || $dailyVisitors === null ? [] : $rates,
        ];
    }
}
