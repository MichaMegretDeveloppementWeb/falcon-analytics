<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Widgets;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Repositories\DashboardReadRepository;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Reactive;
use Livewire\Component;

/**
 * Deferred traffic trend: loads after the page paints (skeleton placeholder
 * meanwhile) and stays in sync with the parent's period filter.
 */
#[Lazy]
final class TrendChart extends Component
{
    #[Reactive]
    public int $period = 30;

    #[Reactive]
    public string $subject = '';

    public function placeholder(): View
    {
        return view('analytics::livewire.dashboard.widgets.trend-chart-placeholder');
    }

    public function render(DashboardReadRepository $repository): View
    {
        $range = Period::ofDays($this->period);
        $trend = $repository->dailyTrend($range, $this->subject !== '' ? $this->subject : null);

        return view('analytics::livewire.dashboard.widgets.trend-chart', [
            'labels' => array_map(fn ($point) => $point->date->isoFormat('D MMM'), $trend),
            'points' => array_map(fn ($point) => $point->sessions, $trend),
        ]);
    }
}
