<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Repositories\DashboardReadRepository;
use Falcon\Analytics\Services\Dashboard\EngagementMetricsCalculator;
use Falcon\Analytics\Services\Dashboard\OverviewMetricsCalculator;
use Illuminate\Contracts\View\View;

/**
 * Dashboard digest: headline counters with period-over-period deltas, a traffic
 * trend, and the visitor and engagement widgets (sources, countries, top pages,
 * top clicks) over the selected period.
 */
final class OverviewPage extends DashboardComponent
{
    public function render(DashboardReadRepository $repository, EngagementMetricsCalculator $engagement, OverviewMetricsCalculator $overview): View
    {
        $period = $this->currentPeriod();
        $subjectType = $this->subjectType();

        $spotlight = $repository->spotlightCounts($subjectType);
        $newVsReturning = $repository->newVsReturning($period, $subjectType);
        $newVsReturningPrevious = $repository->newVsReturning($period->previous(), $subjectType);

        return view('analytics::livewire.dashboard.overview', [
            'range' => $period,
            'headline' => $engagement->headline(
                $repository->headlineCounts($period, $subjectType),
                $repository->headlineCounts($period->previous(), $subjectType),
            ),
            'sparklines' => $engagement->sparklines($repository->sparklineRows($period, $subjectType), $period),
            'spotlight' => $engagement->spotlight($spotlight['today'], $spotlight['yesterday']),
            'newVisitorRate' => $overview->newVisitorRate($newVsReturning, $newVsReturningPrevious),
            'newVsReturning' => $newVsReturning,
            'devices' => $repository->sessionsByDevice($period, $subjectType),
            'topSources' => $repository->topSources($period, $subjectType),
            'topLocalities' => $repository->topLocalities($period, $subjectType),
            'topPages' => $repository->topPages($period, $subjectType),
            'topClicks' => $repository->topClicks($period, $subjectType),
            ...$this->filterData(),
        ])->layout($this->layoutName(), ['title' => __('Vue d\'ensemble').' · '.__('Analytics')]);
    }
}
