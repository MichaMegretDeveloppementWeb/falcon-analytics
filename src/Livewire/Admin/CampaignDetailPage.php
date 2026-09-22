<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin;

use Falcon\Analytics\Actions\DeleteAdAction;
use Falcon\Analytics\Actions\DeleteCampaignAction;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Livewire\Admin\Concerns\EditsAd;
use Falcon\Analytics\Livewire\Admin\Concerns\EditsCampaign;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Throwable;

/**
 * A single campaign in detail: its headline traffic over the period, its identity
 * and URL conditions, and the table of its ads with per-ad traffic and conversion
 * objectives. Ads and objectives are managed here; the campaign can be edited or
 * deleted.
 *
 * @internal
 */
final class CampaignDetailPage extends DashboardComponent
{
    use EditsAd;
    use EditsCampaign;

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

    protected function adFormCampaignId(): int
    {
        return $this->campaign->id;
    }

    protected function campaignFormId(): int
    {
        return $this->campaign->id;
    }

    /** Reads the campaign into the form · the modal opens on the answer. */
    public function editCampaign(): bool
    {
        $this->fillCampaignForm($this->campaign);

        return true;
    }

    protected function afterCampaignSaved(): void
    {
        $this->campaign->refresh();
    }

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

    /** Opens a blank ad form · the modal opens on the answer. */
    public function newAd(): bool
    {
        $this->blankAdForm();

        return true;
    }

    /** Whether the ad could be read into the form · the modal opens on a yes. */
    public function editAd(int $id, FunnelRegistry $funnels, EventRegistry $events): bool
    {
        try {
            $ad = Ad::with('objectives')->where('campaign_id', $this->campaign->id)->findOrFail($id);
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Ad.edit_load_failed', [
                'ad_id' => $id,
                'exception' => $e,
            ]);
            $this->dispatch('ui-toast', type: 'danger', title: __('Cette pub est introuvable. Actualisez la page.'));

            return false;
        }

        $this->fillAdForm($ad, $funnels, $events);

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
                    ...$this->adFormOptions($funnels, $events),
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
