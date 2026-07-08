<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Widgets;

use Falcon\Analytics\DTOs\Dashboard\MetricDelta;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Livewire\Dashboard\Concerns\GuardsWidgetRead;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Repositories\Dashboard\MarketingReadRepository;
use Falcon\Analytics\Services\Dashboard\MarketingMetricsCalculator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Deferred marketing dashboard content: KPIs (with sparklines), the sessions/
 * conversions trend and the per-campaign / top-ad performance. Everything relies on
 * a single conversions() computation, so it lives in one widget to compute it once.
 */
#[Lazy]
final class MarketingDashboardContent extends Component
{
    use GuardsWidgetRead;

    public int $period = Period::DEFAULT_DAYS;

    public string $subject = '';

    public function placeholder(): View
    {
        return view('analytics::livewire.dashboard.widgets.dashboard-content-skeleton');
    }

    public function render(MarketingReadRepository $marketing, FunnelRegistry $funnels, MarketingMetricsCalculator $metrics): View
    {
        return $this->guardedWidget(function () use ($marketing, $funnels, $metrics): array {
            $period = Period::ofDays($this->period);
            $previous = $period->previous();
            $subjectType = $this->subject !== '' ? $this->subject : null;

            $headline = $marketing->headline($period, $subjectType);
            $headlinePrevious = $marketing->headline($previous, $subjectType);
            $performance = $marketing->performance($period, $subjectType);
            $conversions = $marketing->conversions($period, $subjectType, $funnels);
            $conversionsPrevious = $marketing->conversions($previous, $subjectType, $funnels);

            $rate = $metrics->rate((float) $conversions['total'], (float) $headline['visitors']);
            $ratePrevious = $metrics->rate((float) $conversionsPrevious['total'], (float) $headlinePrevious['visitors']);

            $trend = $metrics->trend($period, $marketing->dailySessions($period, $subjectType), $conversions['daily']);

            $campaignNames = Campaign::query()->whereIn('id', array_keys($performance['campaigns']))->pluck('name', 'id');
            $adModels = Ad::query()->with('campaign')->whereIn('id', array_keys($performance['ads']))->get();

            $campaignRows = collect($performance['campaigns'])
                ->map(fn (array $row, int $id): array => [
                    'id' => $id,
                    'name' => (string) ($campaignNames[$id] ?? '·'),
                    'conversions' => $conversions['campaigns'][$id] ?? 0,
                    'rate' => $metrics->rate((float) ($conversions['campaigns'][$id] ?? 0), (float) $row['visitors']),
                    ...$row,
                ])
                ->sortByDesc('sessions')
                ->values()
                ->all();

            $adRows = collect($performance['ads'])
                ->map(function (array $row, int $id) use ($adModels, $conversions): array {
                    $ad = $adModels->firstWhere('id', $id);

                    return [
                        'id' => $id,
                        'name' => $ad->name,
                        'campaign' => $ad->campaign->name,
                        'campaign_id' => $ad->campaign_id,
                        'conversions' => $conversions['ads'][$id] ?? 0,
                        ...$row,
                    ];
                })
                ->sortByDesc('sessions')
                ->values()
                ->all();

            return [
                'range' => $period,
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
                'campaignRows' => $campaignRows,
                'adRows' => array_slice($adRows, 0, 6),
            ];
        }, fn (array $data): View => view('analytics::livewire.dashboard.widgets.marketing-dashboard-content', $data));
    }
}
