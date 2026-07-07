<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Widgets;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Repositories\Dashboard\EventReadRepository;
use Falcon\Analytics\Repositories\Dashboard\OverviewReadRepository;
use Falcon\Analytics\Services\Dashboard\TrendSeriesCalculator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Reactive;
use Livewire\Component;

/**
 * Deferred traffic trend: loads after the page paints (skeleton placeholder
 * meanwhile) and stays in sync with the parent's period and subject filters.
 * Its query is a single indexed range scan on started_at.
 */
#[Lazy]
final class TrendChart extends Component
{
    #[Reactive]
    public int $period = Period::DEFAULT_DAYS;

    #[Reactive]
    public string $subject = '';

    public function placeholder(): View
    {
        return view('analytics::livewire.dashboard.widgets.trend-chart-placeholder');
    }

    public function render(
        OverviewReadRepository $repository,
        EventReadRepository $eventRepository,
        TrendSeriesCalculator $trends,
        EventRegistry $events,
    ): View {
        $range = Period::ofDays($this->period);
        $subjectType = $this->subject !== '' ? $this->subject : null;

        $trend = $trends->points($repository->trendRows($range, $subjectType), $range);
        $conversionsDaily = $eventRepository->daily($range, $subjectType, $events)['conversions'];

        return view('analytics::livewire.dashboard.widgets.trend-chart', [
            'labels' => array_map(fn ($point) => $point->date->isoFormat('D MMM'), $trend),
            'points' => array_map(fn ($point) => $point->sessions, $trend),
            'conversions' => array_map(fn ($point) => $conversionsDaily[$point->date->toDateString()] ?? 0, $trend),
        ]);
    }
}
