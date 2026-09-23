<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin\Widgets;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Livewire\Admin\Concerns\GuardsWidgetRead;
use Falcon\Analytics\Repositories\Dashboard\OverviewReadRepository;
use Falcon\Analytics\Services\Dashboard\EventNames;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Deferred content-engagement block: the most viewed pages and the most clicked
 * elements. Loaded once after the page paints.
 *
 * @internal
 */
#[Lazy]
final class OverviewContent extends Component
{
    use GuardsWidgetRead;

    public int $period = Period::DEFAULT_DAYS;

    public string $subject = '';

    public function placeholder(): View
    {
        return view('analytics::livewire.dashboard.widgets.section-skeleton');
    }

    public function render(OverviewReadRepository $repository, EventNames $names): View
    {
        return $this->guardedWidget(function () use ($repository, $names): array {
            $range = Period::ofDays($this->period);
            $subjectType = $this->subject !== '' ? $this->subject : null;

            return [
                'topPages' => $repository->topPages($range, $subjectType),
                'topClicks' => array_map(
                    static fn (array $click): array => [...$click, 'label' => $names->ofRankedClick($click['label'])],
                    $repository->topClicks($range, $subjectType),
                ),
            ];
        }, fn (array $data): View => view('analytics::livewire.dashboard.widgets.overview-content', $data));
    }
}
