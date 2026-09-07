<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Illuminate\Contracts\View\View;

/**
 * Conversion funnels shell: the header and filters paint immediately; evaluating
 * every declared funnel over the current and previous period (the whole weight of
 * the screen) happens in a deferred widget.
 */
final class FunnelsPage extends DashboardComponent
{
    public function render(): View
    {
        return $this->guardedRender(
            fn (): array => [
                'range' => $this->currentPeriod(),
                ...$this->filterData(),
            ],
            fn (array $data): View => view('analytics::livewire.dashboard.funnels', $data),
        );
    }
}
