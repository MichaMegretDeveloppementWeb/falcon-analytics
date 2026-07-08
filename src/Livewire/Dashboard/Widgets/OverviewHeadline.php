<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Widgets;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Livewire\Dashboard\Concerns\GuardsWidgetRead;
use Falcon\Analytics\Repositories\Dashboard\EngagementReadRepository;
use Falcon\Analytics\Services\Dashboard\EngagementMetricsCalculator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Deferred headline row: the four KPI cards (with their sparklines and today /
 * yesterday spotlight) and the engagement statistics, all from a single engagement
 * read. Deferred so the page shell and its heavier sections paint before the
 * sparkline canvases render.
 */
#[Lazy]
final class OverviewHeadline extends Component
{
    use GuardsWidgetRead;

    public int $period = Period::DEFAULT_DAYS;

    public string $subject = '';

    public function placeholder(): View
    {
        return view('analytics::livewire.dashboard.widgets.kpi-row-skeleton');
    }

    public function render(EngagementReadRepository $engagementRepository, EngagementMetricsCalculator $engagement): View
    {
        return $this->guardedWidget(function () use ($engagementRepository, $engagement): array {
            $period = Period::ofDays($this->period);
            $subjectType = $this->subject !== '' ? $this->subject : null;
            $spotlight = $engagementRepository->spotlightCounts($subjectType);

            return [
                'range' => $period,
                'headline' => $engagement->headline(
                    $engagementRepository->headlineCounts($period, $subjectType),
                    $engagementRepository->headlineCounts($period->previous(), $subjectType),
                ),
                'sparklines' => $engagement->sparklines($engagementRepository->sparklineRows($period, $subjectType), $period),
                'spotlight' => $engagement->spotlight($spotlight['today'], $spotlight['yesterday']),
            ];
        }, fn (array $data): View => view('analytics::livewire.dashboard.widgets.overview-headline', $data));
    }
}
