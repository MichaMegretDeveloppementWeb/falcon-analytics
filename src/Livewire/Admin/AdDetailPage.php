<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin;

use Falcon\Analytics\DTOs\Dashboard\Marketing\AdDetail;
use Falcon\Analytics\Models\Ad;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
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
    /** What the screen shows of the ad's campaign. */
    private const CAMPAIGN = 'campaign:id,name,platform';

    #[Locked]
    public int $adId;

    /** The ad as this request read it · kept for the request, never between two. */
    private ?Ad $read = null;

    public function mount(Ad $ad): void
    {
        $this->adId = $ad->id;
        $this->read = $ad->load(self::CAMPAIGN);
    }

    /** Draws the page again once its form has written · the render reads the ad afresh. */
    #[On('an-ads-changed')]
    public function refresh(): void {}

    public function render(): View
    {
        return $this->guardedRender(
            fn (): array => [
                'detail' => AdDetail::of($this->read ??= Ad::query()->with(self::CAMPAIGN)->findOrFail($this->adId)),
                'range' => $this->currentPeriod(),
                ...$this->filterData(),
            ],
            fn (array $data): View => view('analytics::livewire.dashboard.marketing-ad-detail', $data),
        );
    }
}
