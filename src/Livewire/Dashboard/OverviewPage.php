<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Repositories\Dashboard\EngagementReadRepository;
use Falcon\Analytics\Repositories\Dashboard\EventReadRepository;
use Falcon\Analytics\Repositories\Dashboard\OverviewReadRepository;
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
    public function render(
        EngagementReadRepository $engagementRepository,
        OverviewReadRepository $overviewRepository,
        EventReadRepository $eventRepository,
        EngagementMetricsCalculator $engagement,
        OverviewMetricsCalculator $overview,
        EventRegistry $events,
    ): View {
        return $this->guardedRender(
            function () use ($engagementRepository, $overviewRepository, $eventRepository, $engagement, $overview, $events): array {
                $period = $this->currentPeriod();
                $subjectType = $this->subjectType();

                $spotlight = $engagementRepository->spotlightCounts($subjectType);
                $newVsReturning = $overviewRepository->newVsReturning($period, $subjectType);
                $newVsReturningPrevious = $overviewRepository->newVsReturning($period->previous(), $subjectType);

                $eventBreakdown = $eventRepository->eventBreakdown($period, $subjectType, $events);
                $conversions = array_values(array_filter($eventBreakdown, fn (array $row): bool => $row['isConversion']));

                return [
                    'range' => $period,
                    'headline' => $engagement->headline(
                        $engagementRepository->headlineCounts($period, $subjectType),
                        $engagementRepository->headlineCounts($period->previous(), $subjectType),
                    ),
                    'sparklines' => $engagement->sparklines($engagementRepository->sparklineRows($period, $subjectType), $period),
                    'spotlight' => $engagement->spotlight($spotlight['today'], $spotlight['yesterday']),
                    'newVisitorRate' => $overview->newVisitorRate($newVsReturning, $newVsReturningPrevious),
                    'newVsReturning' => $newVsReturning,
                    'devices' => $overviewRepository->sessionsByDevice($period, $subjectType),
                    'topSources' => $overviewRepository->topSources($period, $subjectType),
                    'topLocalities' => $overviewRepository->topLocalities($period, $subjectType),
                    'topPages' => $overviewRepository->topPages($period, $subjectType),
                    'topClicks' => $overviewRepository->topClicks($period, $subjectType),
                    'topConversions' => array_slice($conversions, 0, 6),
                    'topEvents' => array_slice($eventBreakdown, 0, 6),
                    'eventsRoute' => route(config('analytics.dashboard.route_name', 'analytics').'.events'),
                    ...$this->filterData(),
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.overview', $data)
                ->layout($this->layoutName(), ['title' => __('Vue d\'ensemble').' · '.__('Analytics')]),
        );
    }
}
