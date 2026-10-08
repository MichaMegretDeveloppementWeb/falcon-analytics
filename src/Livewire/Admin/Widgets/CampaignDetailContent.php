<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin\Widgets;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Enums\Authorization\Ability;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Livewire\Admin\Concerns\GuardsWidgetRead;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Services\Dashboard\MarketingMetricsCalculator;
use Falcon\Analytics\Services\Dashboard\MarketingReportBuilder;
use Falcon\Analytics\Support\SubjectFilter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Deferred campaign-detail performance: KPIs (with sparklines), the sessions/
 * conversions trend and the per-objective conversion breakdown, from a single
 * campaignReport + conversions read. The parent keeps its ads table inline (with its
 * CRUD); this widget dispatches the per-ad traffic and conversion figures to it, so
 * those reads happen once here and fill the table's metric cells when they land.
 *
 * @internal
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

    public function render(FunnelRegistry $funnels, EventRegistry $events, MarketingReportBuilder $marketing, MarketingMetricsCalculator $metrics): View
    {
        return $this->guardedWidget(function () use ($funnels, $events, $marketing, $metrics): array {
            $campaign = Campaign::query()->findOrFail($this->refId);
            $period = Period::ofDays($this->period);
            $subjectType = SubjectFilter::chosen($this->subject);

            $report = $marketing->campaignReport($period, $subjectType, $campaign);
            $previous = $marketing->campaignReport($period->previous(), $subjectType, $campaign);

            $conversions = $marketing->conversions($period, $subjectType, $funnels);
            $conversionsPrevious = $marketing->conversions($period->previous(), $subjectType, $funnels);
            $campaignConversions = $conversions === null ? null : $conversions['campaigns'][$campaign->id] ?? 0;
            $campaignConversionsPrevious = $conversionsPrevious === null ? null : $conversionsPrevious['campaigns'][$campaign->id] ?? 0;
            $figures = $metrics->headline($report, $previous, $campaignConversions, $campaignConversionsPrevious);

            $trend = $metrics->trend($period, $report['daily'], $conversions === null ? null : $conversions['campaignDaily'][$campaign->id] ?? [], $report['dailyVisitors']);

            $activeAds = $marketing->activeAdsOf($campaign);

            $this->dispatch('an-campaign-metrics-loaded', adMetrics: $report['ads'], adConversions: $conversions['ads'] ?? null);

            return [
                'sessions' => $report['sessions'],
                'visitors' => $report['visitors'],
                'sessionsDelta' => $figures['sessionsDelta'],
                'visitorsDelta' => $figures['visitorsDelta'],
                'conversions' => $campaignConversions,
                'conversionsDelta' => $figures['conversionsDelta'],
                'conversionsTrend' => $trend['conversions'],
                'rateLabel' => $figures['rate'] === null ? '' : $metrics->rateLabel($figures['rate']),
                'rateDelta' => $figures['rateDelta'],
                'rateTrend' => $trend['rates'],
                'conversionElements' => $marketing->conversionElements($period, $subjectType, $funnels, $events, $activeAds),
                'trendLabels' => $trend['labels'],
                'trendData' => $trend['sessions'],
                'visitorsTrend' => $trend['visitors'],
                'truncatedAt' => $marketing->truncatedAt($period, $subjectType) ?? $marketing->truncatedAt($period->previous(), $subjectType),
                'mayOpenAds' => Gate::allows(Ability::Ads),
            ];
        }, fn (array $data): View => view('analytics::livewire.dashboard.widgets.campaign-detail-content', $data));
    }

    protected function unreadableTitle(): string
    {
        return __('Impossible de charger les résultats de cette campagne');
    }
}
