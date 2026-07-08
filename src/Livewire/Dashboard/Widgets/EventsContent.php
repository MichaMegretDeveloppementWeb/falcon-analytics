<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Widgets;

use Falcon\Analytics\DTOs\Dashboard\MetricDelta;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Repositories\Dashboard\EventReadRepository;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Deferred events content: the KPI row (with sparklines), the events/conversions
 * trend and the full per-event breakdown table. The daily series feeds both the
 * sparklines and the trend, and the breakdown feeds both the KPI totals and the
 * table, so a single read of each backs the whole screen, no duplicate query.
 */
#[Lazy]
final class EventsContent extends Component
{
    public int $period = Period::DEFAULT_DAYS;

    public string $subject = '';

    public function placeholder(): View
    {
        return view('analytics::livewire.dashboard.widgets.dashboard-content-skeleton');
    }

    public function render(EventReadRepository $repository, EventRegistry $events): View
    {
        $range = Period::ofDays($this->period);
        $subjectType = $this->subject !== '' ? $this->subject : null;

        $breakdown = $repository->eventBreakdown($range, $subjectType, $events);
        $headline = $repository->totals($breakdown);
        $headlinePrevious = $repository->headline($range->previous(), $subjectType, $events);
        $daily = $repository->daily($range, $subjectType, $events);

        $labels = [];
        $eventsData = [];
        $conversionsData = [];
        foreach ($range->eachDay() as $day) {
            $key = $day->toDateString();
            $labels[] = $day->isoFormat('D MMM');
            $eventsData[] = $daily['events'][$key] ?? 0;
            $conversionsData[] = $daily['conversions'][$key] ?? 0;
        }

        return view('analytics::livewire.dashboard.widgets.events-content', [
            'events' => $headline['events'],
            'conversions' => $headline['conversions'],
            'value' => $headline['value'],
            'eventsDelta' => new MetricDelta((float) $headline['events'], (float) $headlinePrevious['events']),
            'conversionsDelta' => new MetricDelta((float) $headline['conversions'], (float) $headlinePrevious['conversions']),
            'valueDelta' => new MetricDelta($headline['value'], $headlinePrevious['value']),
            'labels' => $labels,
            'eventsData' => $eventsData,
            'conversionsData' => $conversionsData,
            'breakdown' => $breakdown,
        ]);
    }
}
