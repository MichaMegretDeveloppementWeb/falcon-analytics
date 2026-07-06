<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\MetricDelta;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Repositories\Dashboard\MarketingReadRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

/**
 * The marketing overview: headline figures with sparklines and deltas, a trend,
 * and the per-campaign / top-ad performance, all over the selected period.
 */
final class MarketingDashboardPage extends DashboardComponent
{
    public function render(MarketingReadRepository $marketing, FunnelRegistry $funnels): View
    {
        return $this->guardedRender(
            function () use ($marketing, $funnels): array {
                $period = $this->currentPeriod();
                $previous = $period->previous();
                $subjectType = $this->subjectType();

                $headline = $marketing->headline($period, $subjectType);
                $headlinePrevious = $marketing->headline($previous, $subjectType);
                $performance = $marketing->performance($period, $subjectType);
                $conversions = $marketing->conversions($period, $subjectType, $funnels);
                $conversionsPrevious = $marketing->conversions($previous, $subjectType, $funnels);

                $rate = $headline['visitors'] > 0 ? $conversions['total'] / $headline['visitors'] * 100 : 0.0;
                $ratePrevious = $headlinePrevious['visitors'] > 0 ? $conversionsPrevious['total'] / $headlinePrevious['visitors'] * 100 : 0.0;

                $trend = [];
                foreach ($period->eachDay() as $day) {
                    $trend[$day->toDateString()] = 0;
                }
                foreach ($marketing->dailySessions($period, $subjectType) as $day => $count) {
                    if (array_key_exists($day, $trend)) {
                        $trend[$day] = $count;
                    }
                }

                $campaignNames = Campaign::query()->whereIn('id', array_keys($performance['campaigns']))->pluck('name', 'id');
                $adModels = Ad::query()->with('campaign')->whereIn('id', array_keys($performance['ads']))->get();

                $campaignRows = collect($performance['campaigns'])
                    ->map(fn (array $row, int $id): array => [
                        'id' => $id,
                        'name' => (string) ($campaignNames[$id] ?? '—'),
                        'conversions' => $conversions['campaigns'][$id] ?? 0,
                        'rate' => $row['visitors'] > 0 ? ($conversions['campaigns'][$id] ?? 0) / $row['visitors'] * 100 : 0.0,
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
                    'rateLabel' => number_format($rate, 1, ',', ' ')."\u{00A0}%",
                    'rateDelta' => new MetricDelta($rate, $ratePrevious),
                    'campaignCount' => Campaign::query()->count(),
                    'adCount' => Ad::query()->count(),
                    'trendLabels' => array_map(fn (string $d): string => Carbon::parse($d)->isoFormat('D MMM'), array_keys($trend)),
                    'trendData' => array_values($trend),
                    'campaignRows' => $campaignRows,
                    'adRows' => array_slice($adRows, 0, 6),
                    ...$this->filterData(),
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.marketing-dashboard', $data)
                ->layout($this->layoutName('marketing'), ['title' => __('Vue d\'ensemble').' · '.__('Marketing')]),
        );
    }
}
