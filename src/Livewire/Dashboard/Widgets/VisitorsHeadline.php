<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Widgets;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Repositories\Dashboard\VisitorListReadRepository;
use Falcon\Analytics\Services\Dashboard\VisitorMetricsCalculator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Deferred headline for the visitors screen: four KPI cards with sparklines
 * (visitors, new, returning, sessions per visitor) from a single metrics
 * computation. Deferred so the visitor list and shell paint first.
 */
#[Lazy]
final class VisitorsHeadline extends Component
{
    public int $period = Period::DEFAULT_DAYS;

    public string $subject = '';

    public function placeholder(): View
    {
        return view('analytics::livewire.dashboard.widgets.kpi-row-skeleton');
    }

    public function render(VisitorListReadRepository $repository, VisitorMetricsCalculator $metrics): View
    {
        $period = Period::ofDays($this->period);
        $subjectType = $this->subject !== '' ? $this->subject : null;

        return view('analytics::livewire.dashboard.widgets.visitors-headline', [
            'metrics' => $metrics->compute(
                $repository->visitorCounts($period, $subjectType),
                $repository->visitorCounts($period->previous(), $subjectType),
                $repository->visitorDailyRows($period, $subjectType),
                $period,
            ),
        ]);
    }
}
