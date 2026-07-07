<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\MetricDelta;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Repositories\Dashboard\EventReadRepository;
use Illuminate\Contracts\View\View;

/**
 * Site-wide events and conversions: headline figures, a trend overlaying events and
 * conversions, and the full per-event breakdown with occurrence counts.
 */
final class EventsPage extends DashboardComponent
{
    public function render(EventReadRepository $repository, EventRegistry $events): View
    {
        return $this->guardedRender(
            function () use ($repository, $events): array {
                $period = $this->currentPeriod();
                $subjectType = $this->subjectType();

                $breakdown = $repository->eventBreakdown($period, $subjectType, $events);
                $headline = $repository->totals($breakdown);
                $headlinePrevious = $repository->headline($period->previous(), $subjectType, $events);

                return [
                    'range' => $period,
                    'events' => $headline['events'],
                    'conversions' => $headline['conversions'],
                    'value' => $headline['value'],
                    'eventsDelta' => new MetricDelta((float) $headline['events'], (float) $headlinePrevious['events']),
                    'conversionsDelta' => new MetricDelta((float) $headline['conversions'], (float) $headlinePrevious['conversions']),
                    'valueDelta' => new MetricDelta($headline['value'], $headlinePrevious['value']),
                    'breakdown' => $breakdown,
                    ...$this->filterData(),
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.events', $data)
                ->layout($this->layoutName(), ['title' => __('Événements').' · '.__('Analytics')]),
        );
    }
}
