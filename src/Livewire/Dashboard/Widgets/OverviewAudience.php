<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Widgets;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Repositories\Dashboard\OverviewReadRepository;
use Falcon\Analytics\Services\Dashboard\OverviewMetricsCalculator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Deferred audience block: new vs returning and device split, loaded once after the
 * page paints. Owns the newVsReturning data (donut + the new-visitor rate stat).
 */
#[Lazy]
final class OverviewAudience extends Component
{
    public int $period = Period::DEFAULT_DAYS;

    public string $subject = '';

    public function placeholder(): View
    {
        return view('analytics::livewire.dashboard.widgets.section-skeleton');
    }

    public function render(OverviewReadRepository $repository, OverviewMetricsCalculator $overview): View
    {
        $range = Period::ofDays($this->period);
        $subjectType = $this->subject !== '' ? $this->subject : null;

        $newVsReturning = $repository->newVsReturning($range, $subjectType);

        return view('analytics::livewire.dashboard.widgets.overview-audience', [
            'newVsReturning' => $newVsReturning,
            'devices' => $repository->sessionsByDevice($range, $subjectType),
            'newVisitorRate' => $overview->newVisitorRate($newVsReturning, $repository->newVsReturning($range->previous(), $subjectType)),
        ]);
    }
}
