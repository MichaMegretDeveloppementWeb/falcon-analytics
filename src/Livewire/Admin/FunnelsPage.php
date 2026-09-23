<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin;

use Falcon\Analytics\Enums\Authorization\Ability;
use Falcon\Analytics\Livewire\Admin\Concerns\AsksTheScreenAbility;
use Illuminate\Contracts\View\View;

/**
 * Conversion funnels shell: the header and filters paint immediately; evaluating
 * every declared funnel over the current and previous period (the whole weight of
 * the screen) happens in a deferred widget.
 *
 * @internal
 */
final class FunnelsPage extends DashboardComponent
{
    use AsksTheScreenAbility;

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

    protected function screenAbility(): Ability
    {
        return Ability::Funnels;
    }
}
