<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin;

use Falcon\Analytics\Actions\DeleteAdAction;
use Falcon\Analytics\Actions\DeleteCampaignAction;
use Falcon\Analytics\DTOs\Dashboard\Marketing\AdRow;
use Falcon\Analytics\DTOs\Dashboard\Marketing\CampaignDetail;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Services\Dashboard\ObjectiveLabels;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Throwable;

/**
 * A single campaign in detail: its headline traffic over the period, its identity
 * and URL conditions, and the table of its ads with per-ad traffic and conversion
 * objectives. Ads and objectives are managed here, by the two forms laid once on
 * the page; the campaign can be edited or deleted.
 *
 * @internal
 */
final class CampaignDetailPage extends DashboardComponent
{
    #[Locked]
    public int $campaignId;

    public ?int $deleteAdId = null;

    public string $deleteAdLabel = '';

    /**
     * Per-ad traffic (sessions/visitors) keyed by ad id, filled from the deferred
     * content widget so the inline ads table shows metrics without the page shell
     * carrying the heavy campaignReport read.
     *
     * @var array<int, array{sessions: int, visitors: int}>
     */
    public array $adMetrics = [];

    /** @var array<int, int> */
    public array $adConversions = [];

    /** The campaign as this request read it · kept for the request, never between two. */
    private ?Campaign $read = null;

    public function mount(Campaign $campaign): void
    {
        $this->campaignId = $campaign->id;
        $this->read = $campaign;
    }

    /** Draws the page again once one of its forms has written · the render reads it afresh. */
    #[On('an-campaigns-changed')]
    #[On('an-ads-changed')]
    public function refresh(): void {}

    /** Whether the campaign was deleted · on a yes the page leads back to the list. */
    public function deleteCampaignConfirmed(DeleteCampaignAction $action): bool
    {
        try {
            $action->execute($this->campaignId);
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Campaign.delete_failed', [
                'campaign_id' => $this->campaignId,
                'exception' => $e,
            ]);
            $this->dispatch('ui-toast', type: 'danger', title: __('La suppression de la campagne a échoué. Réessayez.'));

            return false;
        }

        $this->redirect(route('analytics.admin.marketing.campaigns'));

        return true;
    }

    /** Whether there is an ad of this campaign to ask about · the confirmation opens on a yes. */
    public function confirmDeleteAd(int $id): bool
    {
        $name = Ad::query()->where('campaign_id', $this->campaignId)->whereKey($id)->value('name');

        if (! is_string($name)) {
            $this->dispatch('ui-toast', type: 'danger', title: __('Cette pub est introuvable. Actualisez la page.'));

            return false;
        }

        $this->deleteAdId = $id;
        $this->deleteAdLabel = $name;

        return true;
    }

    /** Whether the ad was deleted · the confirmation closes on a yes. */
    public function deleteAdConfirmed(DeleteAdAction $action): bool
    {
        if ($this->deleteAdId === null) {
            return false;
        }

        try {
            $action->execute($this->deleteAdId, $this->campaignId);
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Ad.delete_failed', [
                'ad_id' => $this->deleteAdId,
                'exception' => $e,
            ]);
            $this->dispatch('ui-toast', type: 'danger', title: __('La suppression de la pub a échoué. Réessayez.'));

            return false;
        }

        return true;
    }

    /**
     * @param  array<int, array{sessions: int, visitors: int}>  $adMetrics
     * @param  array<int, int>  $adConversions
     */
    #[On('an-campaign-metrics-loaded')]
    public function fillAdMetrics(array $adMetrics, array $adConversions): void
    {
        $this->adMetrics = $adMetrics;
        $this->adConversions = $adConversions;
    }

    public function render(ObjectiveLabels $objectives): View
    {
        return $this->guardedRender(
            function () use ($objectives): array {
                $campaign = $this->read ??= Campaign::query()->findOrFail($this->campaignId);

                return [
                    'detail' => CampaignDetail::of($campaign),
                    'range' => $this->currentPeriod(),
                    'ads' => $campaign->ads()->with('objectives:id,ad_id,type,reference')->orderBy('name')->get()
                        ->map(fn (Ad $ad): AdRow => AdRow::of($ad, $campaign->name, $objectives->tagsOf($ad->objectives)))
                        ->all(),
                    ...$this->filterData(),
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.marketing-campaign-detail', $data),
        );
    }
}
