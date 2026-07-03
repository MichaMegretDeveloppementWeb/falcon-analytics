<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Widgets;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Repositories\OverviewReadRepository;
use Falcon\Analytics\Services\Dashboard\TrendSeriesCalculator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Reactive;
use Livewire\Component;

/**
 * Traffic trend chart, rendered with the page (its query is a single indexed
 * range scan) and kept in sync with the parent's period and subject filters.
 */
final class TrendChart extends Component
{
    #[Reactive]
    public int $period = Period::DEFAULT_DAYS;

    #[Reactive]
    public string $subject = '';

    public function render(OverviewReadRepository $repository, TrendSeriesCalculator $trends): View
    {
        $range = Period::ofDays($this->period);
        $trend = $trends->points($repository->trendRows($range, $this->subject !== '' ? $this->subject : null), $range);

        return view('analytics::livewire.dashboard.widgets.trend-chart', [
            'labels' => array_map(fn ($point) => $point->date->isoFormat('D MMM'), $trend),
            'points' => array_map(fn ($point) => $point->sessions, $trend),
        ]);
    }
}
