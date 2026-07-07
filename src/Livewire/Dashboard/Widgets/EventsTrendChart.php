<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Widgets;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Repositories\Dashboard\EventReadRepository;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Deferred events-and-conversions trend: loads after the page paints (skeleton
 * meanwhile). The parent gives it a wire:key built from the period and subject, so
 * a filter change re-mounts it with the new values — no reactive props leak to
 * sibling components.
 */
#[Lazy]
final class EventsTrendChart extends Component
{
    public int $period = Period::DEFAULT_DAYS;

    public string $subject = '';

    public function placeholder(): View
    {
        return view('analytics::livewire.dashboard.widgets.trend-chart-placeholder');
    }

    public function render(EventReadRepository $repository, EventRegistry $events): View
    {
        $range = Period::ofDays($this->period);
        $daily = $repository->daily($range, $this->subject !== '' ? $this->subject : null, $events);

        $labels = [];
        $eventsData = [];
        $conversions = [];
        foreach ($range->eachDay() as $day) {
            $key = $day->toDateString();
            $labels[] = $day->isoFormat('D MMM');
            $eventsData[] = $daily['events'][$key] ?? 0;
            $conversions[] = $daily['conversions'][$key] ?? 0;
        }

        return view('analytics::livewire.dashboard.widgets.events-trend-chart', [
            'labels' => $labels,
            'eventsData' => $eventsData,
            'conversions' => $conversions,
        ]);
    }
}
