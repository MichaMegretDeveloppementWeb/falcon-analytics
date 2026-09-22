<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin;

use Falcon\Analytics\Actions\DeleteAdAction;
use Falcon\Analytics\Actions\DeleteCampaignAction;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
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
    public Campaign $campaign;

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

    public function mount(Campaign $campaign): void
    {
        $this->campaign = $campaign;
    }

    /** Reads the campaign again once its form has written to it. */
    #[On('an-campaigns-changed')]
    public function refreshCampaign(): void
    {
        $this->campaign->refresh();
    }

    /** Draws the ads again once their form has written to one · the render reads them afresh. */
    #[On('an-ads-changed')]
    public function refreshAds(): void {}

    /** Whether the campaign was deleted · on a yes the page leads back to the list. */
    public function deleteCampaignConfirmed(DeleteCampaignAction $action): bool
    {
        try {
            $action->execute($this->campaign->id);
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Campaign.delete_failed', [
                'campaign_id' => $this->campaign->id,
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
        $name = Ad::query()->where('campaign_id', $this->campaign->id)->whereKey($id)->value('name');

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
            $action->execute($this->deleteAdId, $this->campaign->id);
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

    public function render(FunnelRegistry $funnels, EventRegistry $events): View
    {
        return $this->guardedRender(
            function () use ($funnels, $events): array {
                $campaignAds = $this->campaign->ads()->with('objectives')->orderBy('name')->get();

                return [
                    'range' => $this->currentPeriod(),
                    'ads' => $campaignAds,
                    'objectiveLabels' => $this->objectiveLabels($funnels, $events),
                    ...$this->filterData(),
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.marketing-campaign-detail', $data),
        );
    }

    /**
     * @return array<string, string>
     */
    private function objectiveLabels(FunnelRegistry $funnels, EventRegistry $events): array
    {
        $labels = [];
        foreach ($funnels->all() as $funnel) {
            $labels['funnel:'.$funnel->key] = $funnel->label;
        }
        foreach ($events->all() as $event) {
            $labels['event:'.$event->name] = $event->label;
        }

        return $labels;
    }
}
