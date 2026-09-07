<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Actions\DeleteAdAction;
use Falcon\Analytics\Actions\DeleteCampaignAction;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Livewire\Dashboard\Concerns\EditsAd;
use Falcon\Analytics\Livewire\Dashboard\Concerns\EditsCampaign;
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
 */
final class CampaignDetailPage extends DashboardComponent
{
    use EditsAd;
    use EditsCampaign;

    public Campaign $campaign;

    /** '' | campaign | ad | delete-campaign | delete-ad */
    public string $modal = '';

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

    public function editCampaign(): void
    {
        $this->fillCampaignForm($this->campaign);
        $this->modal = 'campaign';
    }

    protected function afterCampaignSaved(): void
    {
        $this->campaign->refresh();
    }

    public function confirmDeleteCampaign(): void
    {
        $this->modal = 'delete-campaign';
    }

    public function deleteCampaignConfirmed(DeleteCampaignAction $action): void
    {
        try {
            $action->execute($this->campaign->id);
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Campaign.delete_failed', [
                'campaign_id' => $this->campaign->id,
                'exception' => $e,
            ]);
            $this->dispatch('toast', type: 'danger', title: __('La suppression de la campagne a échoué. Réessayez.'));

            return;
        }

        $this->redirect(route(config('analytics.marketing.route_name', 'marketing').'.campaigns'));
    }

    public function newAd(): void
    {
        $this->blankAdForm();
        $this->modal = 'ad';
    }

    public function editAd(int $id, FunnelRegistry $funnels, EventRegistry $events): void
    {
        try {
            $ad = Ad::with('objectives')->where('campaign_id', $this->campaign->id)->findOrFail($id);
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Ad.edit_load_failed', [
                'ad_id' => $id,
                'exception' => $e,
            ]);
            $this->dispatch('toast', type: 'danger', title: __('Cette pub est introuvable. Actualisez la page.'));

            return;
        }

        $this->fillAdForm($ad, $funnels, $events);
        $this->modal = 'ad';
    }

    public function confirmDeleteAd(int $id): void
    {
        $this->deleteAdId = $id;
        $this->deleteAdLabel = (string) Ad::query()->whereKey($id)->value('name');
        $this->modal = 'delete-ad';
    }

    public function deleteAdConfirmed(DeleteAdAction $action): void
    {
        if ($this->deleteAdId !== null) {
            try {
                $action->execute($this->deleteAdId, $this->campaign->id);
            } catch (Throwable $e) {
                Log::channel(config('analytics.log_channel'))->error('Ad.delete_failed', [
                    'ad_id' => $this->deleteAdId,
                    'exception' => $e,
                ]);
                $this->dispatch('toast', type: 'danger', title: __('La suppression de la pub a échoué. Réessayez.'));

                return;
            }
        }

        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->modal = '';
        $this->resetValidation();
    }

    /**
     * @param  array<int, array{sessions: int, visitors: int}>  $adMetrics
     * @param  array<int, int>  $adConversions
     */
    #[On('campaign-metrics-loaded')]
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
