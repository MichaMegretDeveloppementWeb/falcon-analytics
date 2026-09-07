<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Illuminate\Contracts\View\View;

/**
 * Site-wide events and conversions shell: the header and filters paint immediately.
 * The headline figures (with sparklines), the events/conversions trend and the full
 * per-event breakdown load together in one deferred widget, so a single read of the
 * breakdown and of the daily series backs the whole screen.
 */
final class EventsPage extends DashboardComponent
{
    public function render(): View
    {
        return $this->guardedRender(
            fn (): array => [
                'range' => $this->currentPeriod(),
                ...$this->filterData(),
            ],
            fn (array $data): View => view('analytics::livewire.dashboard.events', $data),
        );
    }
}
