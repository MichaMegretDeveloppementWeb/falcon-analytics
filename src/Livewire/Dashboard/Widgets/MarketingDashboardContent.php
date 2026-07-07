<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Widgets;

use Falcon\Analytics\DTOs\Dashboard\MetricDelta;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Repositories\Dashboard\MarketingReadRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
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
    public int $period = Period::DEFAULT_DAYS;

    public string $subject = '';

    public function placeholder(): View
    {
        return view('analytics::livewire.dashboard.widgets.dashboard-content-skeleton');
    }

    public function render(MarketingReadRepository $marketing, FunnelRegistry $funnels): View
    {
        $period = Period::ofDays($this->period);
        $previous = $period->previous();
        $subjectType = $this->subject !== '' ? $this->subject : null;

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

        $conversionsTrend = [];
        $rateTrend = [];
        foreach ($period->eachDay() as $day) {
            $key = $day->toDateString();
            $dayConversions = $conversions['daily'][$key] ?? 0;
            $daySessions = $trend[$key] ?? 0;
            $conversionsTrend[] = $dayConversions;
            $rateTrend[] = $daySessions > 0 ? round($dayConversions / $daySessions * 100, 1) : 0;
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

        return view('analytics::livewire.dashboard.widgets.marketing-dashboard-content', [
            'range' => $period,
            'sessions' => $headline['sessions'],
            'visitors' => $headline['visitors'],
            'sessionsDelta' => new MetricDelta((float) $headline['sessions'], (float) $headlinePrevious['sessions']),
            'visitorsDelta' => new MetricDelta((float) $headline['visitors'], (float) $headlinePrevious['visitors']),
            'conversions' => $conversions['total'],
            'conversionsDelta' => new MetricDelta((float) $conversions['total'], (float) $conversionsPrevious['total']),
            'conversionsTrend' => $conversionsTrend,
            'rateLabel' => number_format($rate, 1, ',', ' ')."\u{00A0}%",
            'rateDelta' => new MetricDelta($rate, $ratePrevious),
            'rateTrend' => $rateTrend,
            'trendLabels' => array_map(fn (string $d): string => Carbon::parse($d)->isoFormat('D MMM'), array_keys($trend)),
            'trendData' => array_values($trend),
            'campaignRows' => $campaignRows,
            'adRows' => array_slice($adRows, 0, 6),
        ]);
    }
}
