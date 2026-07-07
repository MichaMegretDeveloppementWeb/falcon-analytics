<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Repositories\Dashboard\EngagementReadRepository;
use Falcon\Analytics\Services\Dashboard\EngagementMetricsCalculator;
use Illuminate\Contracts\View\View;

/**
 * Dashboard digest shell: the headline KPI counters (with sparklines and the
 * today/yesterday spotlight) render immediately from light engagement reads. Every
 * heavier block — audience, acquisition, content and the events summary — loads as
 * its own deferred widget after the page paints, so the shell never waits for them.
 */
final class OverviewPage extends DashboardComponent
{
    public function render(EngagementReadRepository $engagementRepository, EngagementMetricsCalculator $engagement): View
    {
        return $this->guardedRender(
            function () use ($engagementRepository, $engagement): array {
                $period = $this->currentPeriod();
                $subjectType = $this->subjectType();

                $spotlight = $engagementRepository->spotlightCounts($subjectType);

                return [
                    'range' => $period,
                    'headline' => $engagement->headline(
                        $engagementRepository->headlineCounts($period, $subjectType),
                        $engagementRepository->headlineCounts($period->previous(), $subjectType),
                    ),
                    'sparklines' => $engagement->sparklines($engagementRepository->sparklineRows($period, $subjectType), $period),
                    'spotlight' => $engagement->spotlight($spotlight['today'], $spotlight['yesterday']),
                    ...$this->filterData(),
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.overview', $data)
                ->layout($this->layoutName(), ['title' => __('Vue d\'ensemble').' · '.__('Analytics')]),
        );
    }
}
