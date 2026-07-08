<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Widgets;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Livewire\Dashboard\Concerns\GuardsWidgetRead;
use Falcon\Analytics\Repositories\Dashboard\EventReadRepository;
use Falcon\Analytics\Repositories\Dashboard\OverviewReadRepository;
use Falcon\Analytics\Services\Dashboard\TrendSeriesCalculator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Deferred traffic trend: loads after the page paints (skeleton placeholder
 * meanwhile). The parent gives it a wire:key built from the period and subject, so a
 * filter change re-mounts it with the new values (non-reactive props, so nothing
 * leaks onto sibling components). Its query is a single indexed range scan on
 * started_at.
 */
#[Lazy]
final class TrendChart extends Component
{
    use GuardsWidgetRead;

    public int $period = Period::DEFAULT_DAYS;

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
        return $this->guardedWidget(function () use ($repository, $eventRepository, $trends, $events): array {
            $range = Period::ofDays($this->period);
            $subjectType = $this->subject !== '' ? $this->subject : null;

            $trend = $trends->points($repository->trendRows($range, $subjectType), $range);
            $conversionsDaily = $eventRepository->daily($range, $subjectType, $events)['conversions'];

            return [
                'labels' => array_map(fn ($point) => $point->date->isoFormat('D MMM'), $trend),
                'points' => array_map(fn ($point) => $point->sessions, $trend),
                'conversions' => array_map(fn ($point) => $conversionsDaily[$point->date->toDateString()] ?? 0, $trend),
            ];
        }, fn (array $data): View => view('analytics::livewire.dashboard.widgets.trend-chart', $data));
    }
}
