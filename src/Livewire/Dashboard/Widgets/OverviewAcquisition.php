<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Widgets;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Livewire\Dashboard\Concerns\GuardsWidgetRead;
use Falcon\Analytics\Repositories\Dashboard\OverviewReadRepository;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Deferred acquisition block: top sources (channels) and top localities, each with
 * their previous-period comparison. Loaded once after the page paints.
 */
#[Lazy]
final class OverviewAcquisition extends Component
{
    use GuardsWidgetRead;

    public int $period = Period::DEFAULT_DAYS;

    public string $subject = '';

    public function placeholder(): View
    {
        return view('analytics::livewire.dashboard.widgets.section-skeleton');
    }

    public function render(OverviewReadRepository $repository): View
    {
        return $this->guardedWidget(function () use ($repository): array {
            $range = Period::ofDays($this->period);
            $subjectType = $this->subject !== '' ? $this->subject : null;

            return [
                'topSources' => $repository->topSources($range, $subjectType),
                'topLocalities' => $repository->topLocalities($range, $subjectType),
            ];
        }, fn (array $data): View => view('analytics::livewire.dashboard.widgets.overview-acquisition', $data));
    }
}
