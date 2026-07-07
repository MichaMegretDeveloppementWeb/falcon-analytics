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
 * Deferred events-and-conversions summary: the top conversions and the top events,
 * with a link through to the full events screen. Loaded once after the page paints.
 */
#[Lazy]
final class OverviewEvents extends Component
{
    public int $period = Period::DEFAULT_DAYS;

    public string $subject = '';

    public function placeholder(): View
    {
        return view('analytics::livewire.dashboard.widgets.section-skeleton');
    }

    public function render(EventReadRepository $repository, EventRegistry $events): View
    {
        $range = Period::ofDays($this->period);
        $subjectType = $this->subject !== '' ? $this->subject : null;

        $breakdown = $repository->eventBreakdown($range, $subjectType, $events);
        $conversions = array_values(array_filter($breakdown, fn (array $row): bool => $row['isConversion']));

        return view('analytics::livewire.dashboard.widgets.overview-events', [
            'topConversions' => array_slice($conversions, 0, 6),
            'topEvents' => array_slice($breakdown, 0, 6),
            'eventsRoute' => route(config('analytics.dashboard.route_name', 'analytics').'.events'),
        ]);
    }
}
