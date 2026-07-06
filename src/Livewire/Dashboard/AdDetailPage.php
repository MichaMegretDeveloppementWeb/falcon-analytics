<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\MetricDelta;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Repositories\Dashboard\MarketingReadRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

/**
 * A single ad in detail: its headline traffic over the period with a trend, its
 * parent campaign, URL conditions and conversion objectives. The ad is edited from
 * its campaign's detail page.
 */
final class AdDetailPage extends DashboardComponent
{
    public Ad $ad;

    public function mount(Ad $ad): void
    {
        $this->ad = $ad->load(['campaign', 'objectives']);
    }

    public function render(FunnelRegistry $funnels, EventRegistry $events, MarketingReadRepository $marketing): View
    {
        return $this->guardedRender(
            function () use ($funnels, $events, $marketing): array {
                $period = $this->currentPeriod();
                $subjectType = $this->subjectType();
                $report = $marketing->adReport($period, $subjectType, $this->ad);
                $previous = $marketing->adReport($period->previous(), $subjectType, $this->ad);

                $trend = [];
                foreach ($period->eachDay() as $day) {
                    $trend[$day->toDateString()] = $report['daily'][$day->toDateString()] ?? 0;
                }

                $labels = [];
                foreach ($funnels->all() as $funnel) {
                    $labels['funnel:'.$funnel->key] = $funnel->label;
                }
                foreach ($events->all() as $event) {
                    $labels['event:'.$event->name] = $event->label;
                }

                return [
                    'range' => $period,
                    'sessions' => $report['sessions'],
                    'visitors' => $report['visitors'],
                    'sessionsDelta' => new MetricDelta((float) $report['sessions'], (float) $previous['sessions']),
                    'visitorsDelta' => new MetricDelta((float) $report['visitors'], (float) $previous['visitors']),
                    'trendLabels' => array_map(fn (string $d): string => Carbon::parse($d)->isoFormat('D MMM'), array_keys($trend)),
                    'trendData' => array_values($trend),
                    'objectiveLabels' => $labels,
                    ...$this->filterData(),
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.marketing-ad-detail', $data)
                ->layout($this->layoutName('marketing'), ['title' => $this->ad->name.' · '.__('Marketing')]),
        );
    }
}
