<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Widgets;

use Falcon\Analytics\DTOs\Dashboard\MetricDelta;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Livewire\Dashboard\Concerns\GuardsWidgetRead;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Repositories\Dashboard\MarketingReadRepository;
use Falcon\Analytics\Services\Dashboard\MarketingMetricsCalculator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Deferred campaign-detail performance: KPIs (with sparklines), the sessions/
 * conversions trend and the per-objective conversion breakdown, from a single
 * campaignReport + conversions read. The parent keeps its ads table inline (with its
 * CRUD); this widget dispatches the per-ad traffic and conversion figures to it, so
 * those reads happen once here and fill the table's metric cells when they land.
 */
#[Lazy]
final class CampaignDetailContent extends Component
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
            $campaign = Campaign::query()->findOrFail($this->refId);
            $period = Period::ofDays($this->period);
            $subjectType = $this->subject !== '' ? $this->subject : null;

            $report = $marketing->campaignReport($period, $subjectType, $campaign);
            $previous = $marketing->campaignReport($period->previous(), $subjectType, $campaign);

            $conversions = $marketing->conversions($period, $subjectType, $funnels);
            $conversionsPrevious = $marketing->conversions($period->previous(), $subjectType, $funnels);
            $campaignConversions = $conversions['campaigns'][$campaign->id] ?? 0;
            $campaignConversionsPrevious = $conversionsPrevious['campaigns'][$campaign->id] ?? 0;
            $rate = $metrics->rate((float) $campaignConversions, (float) $report['visitors']);
            $ratePrevious = $metrics->rate((float) $campaignConversionsPrevious, (float) $previous['visitors']);

            $trend = $metrics->trend($period, $report['daily'], $conversions['campaignDaily'][$campaign->id] ?? []);

            $activeAds = $campaign->ads()->where('is_active', true)->get()->all();

            // Hand the per-ad traffic and conversions to the parent's inline ads table,
            // so those reads happen once here rather than blocking the page shell.
            $this->dispatch('campaign-metrics-loaded', adMetrics: $report['ads'], adConversions: $conversions['ads']);

            return [
                'sessions' => $report['sessions'],
                'visitors' => $report['visitors'],
                'sessionsDelta' => new MetricDelta((float) $report['sessions'], (float) $previous['sessions']),
                'visitorsDelta' => new MetricDelta((float) $report['visitors'], (float) $previous['visitors']),
                'conversions' => $campaignConversions,
                'conversionsDelta' => new MetricDelta((float) $campaignConversions, (float) $campaignConversionsPrevious),
                'conversionsTrend' => $trend['conversions'],
                'rateLabel' => $metrics->rateLabel($rate),
                'rateDelta' => new MetricDelta($rate, $ratePrevious),
                'rateTrend' => $trend['rates'],
                'conversionElements' => $marketing->conversionElements($period, $subjectType, $funnels, $events, $activeAds),
                'trendLabels' => $trend['labels'],
                'trendData' => $trend['sessions'],
            ];
        }, fn (array $data): View => view('analytics::livewire.dashboard.widgets.campaign-detail-content', $data));
    }
}
