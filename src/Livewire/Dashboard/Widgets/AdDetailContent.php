<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Widgets;

use Falcon\Analytics\DTOs\Dashboard\MetricDelta;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Repositories\Dashboard\MarketingReadRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Deferred ad-detail performance: KPIs (with sparklines), the sessions/conversions
 * trend and the per-objective conversion breakdown. Read-only — the header, edit
 * button and modal stay on the page shell — so it just loads after the shell paints.
 */
#[Lazy]
final class AdDetailContent extends Component
{
    public int $period = Period::DEFAULT_DAYS;

    public string $subject = '';

    public int $refId = 0;

    public function placeholder(): View
    {
        return view('analytics::livewire.dashboard.widgets.dashboard-content-skeleton');
    }

    public function render(FunnelRegistry $funnels, EventRegistry $events, MarketingReadRepository $marketing): View
    {
        $ad = Ad::query()->findOrFail($this->refId);
        $period = Period::ofDays($this->period);
        $subjectType = $this->subject !== '' ? $this->subject : null;

        $report = $marketing->adReport($period, $subjectType, $ad);
        $previous = $marketing->adReport($period->previous(), $subjectType, $ad);

        $trend = [];
        foreach ($period->eachDay() as $day) {
            $trend[$day->toDateString()] = $report['daily'][$day->toDateString()] ?? 0;
        }

        $conversions = $marketing->conversions($period, $subjectType, $funnels);
        $conversionsPrevious = $marketing->conversions($period->previous(), $subjectType, $funnels);
        $adConversions = $conversions['ads'][$ad->id] ?? 0;
        $adConversionsPrevious = $conversionsPrevious['ads'][$ad->id] ?? 0;
        $rate = $report['visitors'] > 0 ? $adConversions / $report['visitors'] * 100 : 0.0;
        $ratePrevious = $previous['visitors'] > 0 ? $adConversionsPrevious / $previous['visitors'] * 100 : 0.0;

        $adDaily = $conversions['adDaily'][$ad->id] ?? [];
        $conversionsTrend = [];
        $rateTrend = [];
        foreach ($period->eachDay() as $day) {
            $key = $day->toDateString();
            $dayConversions = $adDaily[$key] ?? 0;
            $daySessions = $report['daily'][$key] ?? 0;
            $conversionsTrend[] = $dayConversions;
            $rateTrend[] = $daySessions > 0 ? round($dayConversions / $daySessions * 100, 1) : 0;
        }

        return view('analytics::livewire.dashboard.widgets.ad-detail-content', [
            'sessions' => $report['sessions'],
            'visitors' => $report['visitors'],
            'sessionsDelta' => new MetricDelta((float) $report['sessions'], (float) $previous['sessions']),
            'visitorsDelta' => new MetricDelta((float) $report['visitors'], (float) $previous['visitors']),
            'conversions' => $adConversions,
            'conversionsDelta' => new MetricDelta((float) $adConversions, (float) $adConversionsPrevious),
            'conversionsTrend' => $conversionsTrend,
            'rateLabel' => number_format($rate, 1, ',', ' ')."\u{00A0}%",
            'rateDelta' => new MetricDelta($rate, $ratePrevious),
            'rateTrend' => $rateTrend,
            'conversionElements' => $marketing->conversionElements($period, $subjectType, $funnels, $events, [$ad]),
            'trendLabels' => array_map(fn (string $d): string => Carbon::parse($d)->isoFormat('D MMM'), array_keys($trend)),
            'trendData' => array_values($trend),
        ]);
    }
}
