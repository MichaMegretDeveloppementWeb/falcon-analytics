<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin;

use Falcon\Analytics\Enums\Authorization\Ability;
use Falcon\Analytics\Livewire\Admin\Concerns\AsksTheScreenAbility;
use Illuminate\Contracts\View\View;

/**
 * The marketing overview shell: header and filters render immediately. The KPIs,
 * trend and per-campaign performance all share a single conversions() computation,
 * so they load together as one deferred widget after the page paints.
 *
 * @internal
 */
final class MarketingDashboardPage extends DashboardComponent
{
    use AsksTheScreenAbility;

    public function render(): View
    {
        return $this->guardedRender(
            fn (): array => [
                'range' => $this->currentPeriod(),
                ...$this->filterData(),
            ],
            fn (array $data): View => view('analytics::livewire.dashboard.marketing-dashboard', $data),
        );
    }

    protected function screenAbility(): Ability
    {
        return Ability::MarketingDashboard;
    }
}
