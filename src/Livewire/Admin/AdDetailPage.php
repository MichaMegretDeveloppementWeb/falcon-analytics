<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin;

use Falcon\Analytics\DTOs\Dashboard\Marketing\AdDetail;
use Falcon\Analytics\Enums\Authorization\Ability;
use Falcon\Analytics\Livewire\Admin\Concerns\AsksTheScreenAbility;
use Falcon\Analytics\Models\Ad;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
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
    use AsksTheScreenAbility;

    /** What the screen shows of the ad's campaign. */
    private const CAMPAIGN = 'campaign:id,name,platform';

    #[Locked]
    public int $adId;

    /** The ad as this request read it · kept for the request, never between two. */
    private ?Ad $read = null;

    public function mount(int $adId): void
    {
        $this->adId = $adId;
    }

    /** Draws the page again once its form has written · the render reads the ad afresh. */
    #[On('an-ads-changed')]
    public function refresh(): void {}

    public function render(): View
    {
        return $this->guardedRender(
            function (): array {
                // Read here and not at mount, so that a failure is the error state and not a raw error.
                $ad = ($this->read ??= Ad::query()->findOrFail($this->adId))->load(self::CAMPAIGN);

                return [
                    'detail' => AdDetail::of($ad),
                    'range' => $this->currentPeriod(),
                    'mayEdit' => Gate::allows(Ability::AdsEdit, $ad),
                    'mayOpenCampaigns' => Gate::allows(Ability::Campaigns),
                    ...$this->filterData(),
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.marketing-ad-detail', $data),
        );
    }

    protected function screenAbility(): Ability
    {
        return Ability::Ads;
    }

    /** @param ModelNotFoundException<Model> $gone */
    protected function addressOnceGone(ModelNotFoundException $gone): ?string
    {
        return $gone->getModel() === Ad::class ? route('analytics.admin.marketing.ads.show', $this->adId) : null;
    }

    protected function unreadableTitle(): string
    {
        return __('Impossible de charger cette publicité');
    }
}
