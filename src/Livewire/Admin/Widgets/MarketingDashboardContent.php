<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin\Widgets;

use Falcon\Analytics\DTOs\Dashboard\MetricDelta;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Livewire\Admin\Concerns\GuardsWidgetRead;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Services\Dashboard\MarketingMetricsCalculator;
use Falcon\Analytics\Services\Dashboard\MarketingReportBuilder;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Deferred marketing dashboard content: KPIs (with sparklines), the sessions/
 * conversions trend and the per-campaign / top-ad performance. Everything relies on
 * a single conversions() computation, so it lives in one widget to compute it once.
 *
 * @internal
 */
#[Lazy]
final class MarketingDashboardContent extends Component
{
    use GuardsWidgetRead;

    /** How many ads the performance table lists, the busiest first. */
    private const TOP_ADS = 6;

    public int $period = Period::DEFAULT_DAYS;

    public string $subject = '';

    public function placeholder(): View
    {
        return view('analytics::livewire.dashboard.widgets.dashboard-content-skeleton');
    }

    public function render(MarketingReportBuilder $marketing, FunnelRegistry $funnels, MarketingMetricsCalculator $metrics): View
    {
        return $this->guardedWidget(function () use ($marketing, $funnels, $metrics): array {
            $period = Period::ofDays($this->period);
            $subjectType = $this->subject !== '' ? $this->subject : null;
            $performance = $marketing->performance($period, $subjectType);
            $conversions = $marketing->conversions($period, $subjectType, $funnels);

            return [
                'range' => $period,
                ...$this->figures($marketing, $funnels, $metrics, $period, $subjectType, $conversions),
                'campaignRows' => $this->campaignRows($performance['campaigns'], $conversions['campaigns'], $metrics),
                'adRows' => array_slice($this->adRows($performance['ads'], $conversions['ads']), 0, self::TOP_ADS),
                'truncatedAt' => $marketing->truncatedAt($period, $subjectType) ?? $marketing->truncatedAt($period->previous(), $subjectType),
            ];
        }, fn (array $data): View => view('analytics::livewire.dashboard.widgets.marketing-dashboard-content', $data));
    }

    /**
     * The headline figures, each against the previous period, and the trend.
     *
     * @param  array{total: int, campaigns: array<int, int>, ads: array<int, int>, objectives: array<int, array<string, int>>, daily: array<string, int>, campaignDaily: array<int, array<string, int>>, adDaily: array<int, array<string, int>>}  $conversions  the period's
     * @return array<string, mixed>
     */
    private function figures(MarketingReportBuilder $marketing, FunnelRegistry $funnels, MarketingMetricsCalculator $metrics, Period $period, ?string $subjectType, array $conversions): array
    {
        $previous = $period->previous();
        $headline = $marketing->headline($period, $subjectType);
        $headlinePrevious = $marketing->headline($previous, $subjectType);
        $conversionsPrevious = $marketing->conversions($previous, $subjectType, $funnels);
        $rate = $metrics->rate((float) $conversions['total'], (float) $headline['visitors']);
        $ratePrevious = $metrics->rate((float) $conversionsPrevious['total'], (float) $headlinePrevious['visitors']);
        $trend = $metrics->trend($period, $marketing->dailySessions($period, $subjectType), $conversions['daily']);

        return [
            'sessions' => $headline['sessions'],
            'visitors' => $headline['visitors'],
            'sessionsDelta' => new MetricDelta((float) $headline['sessions'], (float) $headlinePrevious['sessions']),
            'visitorsDelta' => new MetricDelta((float) $headline['visitors'], (float) $headlinePrevious['visitors']),
            'conversions' => $conversions['total'],
            'conversionsDelta' => new MetricDelta((float) $conversions['total'], (float) $conversionsPrevious['total']),
            'conversionsTrend' => $trend['conversions'],
            'rateLabel' => $metrics->rateLabel($rate),
            'rateDelta' => new MetricDelta($rate, $ratePrevious),
            'rateTrend' => $trend['rates'],
            'trendLabels' => $trend['labels'],
            'trendData' => $trend['sessions'],
        ];
    }

    /**
     * One row per campaign that brought traffic, the busiest first.
     *
     * @param  array<int, array{sessions: int, visitors: int}>  $performance  campaign id => traffic
     * @param  array<int, int>  $conversions  campaign id => conversions
     * @return array<int, array{id: int, name: string, conversions: int, rate: float, sessions: int, visitors: int}>
     */
    private function campaignRows(array $performance, array $conversions, MarketingMetricsCalculator $metrics): array
    {
        $names = Campaign::query()->whereIn('id', array_keys($performance))->pluck('name', 'id');

        return collect($performance)
            ->map(fn (array $row, int $id): array => [
                'id' => $id,
                'name' => (string) ($names[$id] ?? '·'),
                'conversions' => $conversions[$id] ?? 0,
                'rate' => $metrics->rate((float) ($conversions[$id] ?? 0), (float) $row['visitors']),
                ...$row,
            ])
            ->sortByDesc('sessions')
            ->values()
            ->all();
    }

    /**
     * One row per ad that brought traffic, the busiest first · an ad gone
     * between the aggregation and this read shows as a dash, as a campaign
     * does, and never takes the dashboard down for one line.
     *
     * @param  array<int, array{sessions: int, visitors: int}>  $performance  ad id => traffic
     * @param  array<int, int>  $conversions  ad id => conversions
     * @return array<int, array{id: int, name: string, campaign: string, campaign_id: int|null, conversions: int, sessions: int, visitors: int}>
     */
    private function adRows(array $performance, array $conversions): array
    {
        $ads = Ad::query()
            ->select(['id', 'name', 'campaign_id'])
            ->with('campaign:id,name')
            ->whereIn('id', array_keys($performance))
            ->get()
            ->keyBy('id');

        return collect($performance)
            ->map(function (array $row, int $id) use ($ads, $conversions): array {
                $ad = $ads->get($id);

                return [
                    'id' => $id,
                    'name' => $ad->name ?? '·',
                    'campaign' => $ad->campaign->name ?? '·',
                    'campaign_id' => $ad?->campaign_id,
                    'conversions' => $conversions[$id] ?? 0,
                    ...$row,
                ];
            })
            ->sortByDesc('sessions')
            ->values()
            ->all();
    }
}
