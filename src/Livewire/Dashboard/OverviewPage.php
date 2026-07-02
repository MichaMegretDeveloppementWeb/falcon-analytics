<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Repositories\DashboardReadRepository;
use Illuminate\Contracts\View\View;

/**
 * Dashboard digest: headline counters with period-over-period deltas, a traffic
 * trend, and the visitor and engagement widgets (sources, countries, top pages,
 * top clicks) over the selected period.
 */
final class OverviewPage extends DashboardComponent
{
    public function render(DashboardReadRepository $repository): View
    {
        $period = $this->currentPeriod();
        $subjectType = $this->subjectType();

        return view('analytics::livewire.dashboard.overview', [
            'range' => $period,
            'headline' => $repository->headline($period, $subjectType),
            'spotlight' => $repository->spotlight($subjectType),
            'topSources' => $repository->topSources($period, $subjectType),
            'topCountries' => $repository->sessionsByCountry($period, $subjectType),
            'topPages' => $repository->topPages($period, $subjectType),
            'topClicks' => $repository->topClicks($period, $subjectType),
            ...$this->filterData(),
        ])->layout($this->layoutName(), ['title' => __('Vue d\'ensemble').' · '.__('Analytics')]);
    }
}
