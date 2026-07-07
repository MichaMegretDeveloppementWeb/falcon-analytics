<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Illuminate\Contracts\View\View;

/**
 * Dashboard digest shell: the header and filters render immediately. The headline
 * KPI row (with sparklines and the today/yesterday spotlight) and every heavier
 * block — audience, acquisition, content and the events summary — each load as their
 * own deferred widget after the page paints, so the shell never waits for them.
 */
final class OverviewPage extends DashboardComponent
{
    public function render(): View
    {
        return $this->guardedRender(
            fn (): array => [
                'range' => $this->currentPeriod(),
                ...$this->filterData(),
            ],
            fn (array $data): View => view('analytics::livewire.dashboard.overview', $data)
                ->layout($this->layoutName(), ['title' => __('Vue d\'ensemble').' · '.__('Analytics')]),
        );
    }
}
