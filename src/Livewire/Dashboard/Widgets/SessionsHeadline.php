<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Widgets;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Repositories\Dashboard\EngagementReadRepository;
use Falcon\Analytics\Services\Dashboard\EngagementMetricsCalculator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Deferred engagement headline for the sessions screen: four KPI cards with
 * sparklines, from a single engagement read. Deferred so the session list (the
 * screen's primary content) and the shell paint before the sparkline canvases.
 */
#[Lazy]
final class SessionsHeadline extends Component
{
    public int $period = Period::DEFAULT_DAYS;

    public string $subject = '';

    public function placeholder(): View
    {
        return view('analytics::livewire.dashboard.widgets.kpi-row-skeleton');
    }

    public function render(EngagementReadRepository $engagementRepository, EngagementMetricsCalculator $engagement): View
    {
        $period = Period::ofDays($this->period);
        $subjectType = $this->subject !== '' ? $this->subject : null;

        return view('analytics::livewire.dashboard.widgets.sessions-headline', [
            'headline' => $engagement->headline(
                $engagementRepository->headlineCounts($period, $subjectType),
                $engagementRepository->headlineCounts($period->previous(), $subjectType),
            ),
            'sparklines' => $engagement->sparklines($engagementRepository->sparklineRows($period, $subjectType), $period),
        ]);
    }
}
