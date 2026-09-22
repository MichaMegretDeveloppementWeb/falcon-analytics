<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin;

use Falcon\Analytics\Models\Ad;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;

/**
 * A single ad in detail: its parent campaign and URL conditions render
 * immediately, with the ad form laid once on the page; its headline traffic,
 * trend and conversion breakdown load in a deferred widget after the shell
 * paints.
 *
 * @internal
 */
final class AdDetailPage extends DashboardComponent
{
    public Ad $ad;

    public function mount(Ad $ad): void
    {
        $this->ad = $ad->load(['campaign', 'objectives']);
    }

    /** Reads the ad again once its form has written to it. */
    #[On('an-ads-changed')]
    public function refreshAd(): void
    {
        $this->ad->refresh()->load(['campaign', 'objectives']);
    }

    public function render(): View
    {
        return $this->guardedRender(
            fn (): array => [
                'range' => $this->currentPeriod(),
                ...$this->filterData(),
            ],
            fn (array $data): View => view('analytics::livewire.dashboard.marketing-ad-detail', $data),
        );
    }
}
