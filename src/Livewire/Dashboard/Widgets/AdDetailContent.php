<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Widgets;

use Falcon\Analytics\DTOs\Dashboard\MetricDelta;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Livewire\Dashboard\Concerns\GuardsWidgetRead;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Repositories\Dashboard\MarketingReadRepository;
use Falcon\Analytics\Services\Dashboard\MarketingMetricsCalculator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Deferred ad-detail performance: KPIs (with sparklines), the sessions/conversions
 * trend and the per-objective conversion breakdown. Read-only (the header, edit
 * button and modal stay on the page shell), so it just loads after the shell paints.
 */
#[Lazy]
final class AdDetailContent extends Component
{
    use GuardsWidgetRead;

    public int $period = Period::DEFAULT_DAYS;

    public string $subject = '';

    public int $refId = 0;

    public function placeholder(): View
    {
        return view('analytics::livewire.dashboard.widgets.dashboard-content-skeleton');
    }

    public function render(FunnelRegistry $funnels, EventRegistry $events, MarketingReadRepository $marketing, MarketingMetricsCalculator $metrics): View
    {
        return $this->guardedWidget(function () use ($funnels, $events, $marketing, $metrics): array {
            $ad = Ad::query()->findOrFail($this->refId);
            $period = Period::ofDays($this->period);
            $subjectType = $this->subject !== '' ? $this->subject : null;

            $report = $marketing->adReport($period, $subjectType, $ad);
            $previous = $marketing->adReport($period->previous(), $subjectType, $ad);

            $conversions = $marketing->conversions($period, $subjectType, $funnels);
            $conversionsPrevious = $marketing->conversions($period->previous(), $subjectType, $funnels);
            $adConversions = $conversions['ads'][$ad->id] ?? 0;
            $adConversionsPrevious = $conversionsPrevious['ads'][$ad->id] ?? 0;
            $rate = $metrics->rate((float) $adConversions, (float) $report['visitors']);
            $ratePrevious = $metrics->rate((float) $adConversionsPrevious, (float) $previous['visitors']);

            $trend = $metrics->trend($period, $report['daily'], $conversions['adDaily'][$ad->id] ?? []);

            return [
                'sessions' => $report['sessions'],
                'visitors' => $report['visitors'],
                'sessionsDelta' => new MetricDelta((float) $report['sessions'], (float) $previous['sessions']),
                'visitorsDelta' => new MetricDelta((float) $report['visitors'], (float) $previous['visitors']),
                'conversions' => $adConversions,
                'conversionsDelta' => new MetricDelta((float) $adConversions, (float) $adConversionsPrevious),
                'conversionsTrend' => $trend['conversions'],
                'rateLabel' => $metrics->rateLabel($rate),
                'rateDelta' => new MetricDelta($rate, $ratePrevious),
                'rateTrend' => $trend['rates'],
                'conversionElements' => $marketing->conversionElements($period, $subjectType, $funnels, $events, [$ad]),
                'trendLabels' => $trend['labels'],
                'trendData' => $trend['sessions'],
            ];
        }, fn (array $data): View => view('analytics::livewire.dashboard.widgets.ad-detail-content', $data));
    }
}
