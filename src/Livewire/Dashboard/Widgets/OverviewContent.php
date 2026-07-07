<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Widgets;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Repositories\Dashboard\OverviewReadRepository;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Deferred content-engagement block: the most viewed pages and the most clicked
 * elements. Loaded once after the page paints.
 */
#[Lazy]
final class OverviewContent extends Component
{
    public int $period = Period::DEFAULT_DAYS;

    public string $subject = '';

    public function placeholder(): View
    {
        return view('analytics::livewire.dashboard.widgets.section-skeleton');
    }

    public function render(OverviewReadRepository $repository): View
    {
        $range = Period::ofDays($this->period);
        $subjectType = $this->subject !== '' ? $this->subject : null;

        return view('analytics::livewire.dashboard.widgets.overview-content', [
            'topPages' => $repository->topPages($range, $subjectType),
            'topClicks' => $repository->topClicks($range, $subjectType),
        ]);
    }
}
