<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Repositories\DashboardReadRepository;
use Illuminate\Contracts\View\View;

/**
 * Dashboard landing page: headline counters, a daily traffic trend, and the top
 * entry pages and acquisition sources over the selected period.
 */
final class OverviewPage extends DashboardComponent
{
    public function render(DashboardReadRepository $repository): View
    {
        $period = $this->currentPeriod();
        $subjectType = $this->subjectType();

        return view('analytics::livewire.dashboard.overview', [
            'range' => $period,
            'metrics' => $repository->metrics($period, $subjectType),
            'trend' => $repository->dailyTrend($period, $subjectType),
            'topPages' => $repository->topLandingPages($period, $subjectType),
            'topSources' => $repository->topSources($period, $subjectType),
            ...$this->filterData(),
        ])->layout($this->layoutName(), ['title' => __('Vue d\'ensemble').' · '.__('Analytics')]);
    }
}
