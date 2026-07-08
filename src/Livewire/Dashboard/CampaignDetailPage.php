<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Livewire\Dashboard\Concerns\EditsAd;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\AdObjective;
use Falcon\Analytics\Models\Campaign;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

/**
 * A single campaign in detail: its headline traffic over the period, its identity
 * and URL conditions, and the table of its ads with per-ad traffic and conversion
 * objectives. Ads and objectives are managed here; the campaign can be edited or
 * deleted.
 */
final class CampaignDetailPage extends DashboardComponent
{
    use EditsAd;

    public Campaign $campaign;

    /** '' | campaign | ad | delete-campaign | delete-ad */
    public string $modal = '';

    public string $campaignName = '';

    public string $campaignPlatform = '';

    /** @var list<array{param: string, value: string}> */
    public array $campaignConditions = [];

    public ?int $deleteAdId = null;

    public string $deleteAdLabel = '';

    public function mount(Campaign $campaign): void
    {
        $this->campaign = $campaign;
    }

    protected function adFormCampaignId(): int
    {
        return $this->campaign->id;
    }

    public function editCampaign(): void
    {
        $this->campaignName = $this->campaign->name;
        $this->campaignPlatform = (string) $this->campaign->platform;
        $this->campaignConditions = $this->campaign->match_conditions ?: [['param' => '', 'value' => '']];
        $this->modal = 'campaign';
    }

    public function addCampaignCondition(): void
    {
        $this->campaignConditions[] = ['param' => '', 'value' => ''];
    }

    public function removeCampaignCondition(int $index): void
    {
        unset($this->campaignConditions[$index]);
    }

    public function saveCampaign(): void
    {
        $this->validate([
            'campaignName' => ['required', 'string', 'max:150'],
            'campaignPlatform' => ['nullable', 'string', 'max:60'],
            'campaignConditions' => ['required', 'array', 'min:1'],
            'campaignConditions.*.param' => ['required', 'string', 'max:100'],
            'campaignConditions.*.value' => ['required', 'string', 'max:150'],
        ], attributes: [
            'campaignName' => __('nom'),
            'campaignConditions.*.param' => __('paramètre'),
            'campaignConditions.*.value' => __('valeur'),
        ]);

        $this->campaign->fill([
            'name' => $this->campaignName,
            'platform' => $this->campaignPlatform !== '' ? $this->campaignPlatform : null,
            'match_conditions' => $this->cleanConditions($this->campaignConditions),
        ])->save();

        $this->modal = '';
    }

    public function confirmDeleteCampaign(): void
    {
        $this->modal = 'delete-campaign';
    }

    public function deleteCampaignConfirmed(): void
    {
        $campaignId = $this->campaign->id;

        DB::transaction(function () use ($campaignId): void {
            $adIds = Ad::query()->where('campaign_id', $campaignId)->pluck('id');
            AdObjective::query()->whereIn('ad_id', $adIds)->delete();
            Ad::query()->where('campaign_id', $campaignId)->delete();
            Campaign::query()->whereKey($campaignId)->delete();
        });

        $this->redirect(route(config('analytics.marketing.route_name', 'marketing').'.campaigns'));
    }

    public function newAd(): void
    {
        $this->blankAdForm();
        $this->modal = 'ad';
    }

    public function editAd(int $id, FunnelRegistry $funnels, EventRegistry $events): void
    {
        $ad = Ad::with('objectives')->where('campaign_id', $this->campaign->id)->findOrFail($id);
        $this->fillAdForm($ad, $funnels, $events);
        $this->modal = 'ad';
    }

    public function confirmDeleteAd(int $id): void
    {
        $this->deleteAdId = $id;
        $this->deleteAdLabel = (string) Ad::query()->whereKey($id)->value('name');
        $this->modal = 'delete-ad';
    }

    public function deleteAdConfirmed(): void
    {
        if ($this->deleteAdId !== null) {
            $adId = $this->deleteAdId;

            DB::transaction(function () use ($adId): void {
                AdObjective::query()->where('ad_id', $adId)->delete();
                Ad::query()->whereKey($adId)->where('campaign_id', $this->campaign->id)->delete();
            });
        }

        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->modal = '';
        $this->resetValidation();
    }

    /**
     * @param  list<array{param: string, value: string}>  $conditions
     * @return list<array{param: string, value: string}>
     */
    private function cleanConditions(array $conditions): array
    {
        $cleaned = array_map(
            fn (array $condition): array => ['param' => trim($condition['param']), 'value' => trim($condition['value'])],
            $conditions,
        );

        return array_values(array_filter($cleaned, fn (array $c): bool => $c['param'] !== '' && $c['value'] !== ''));
    }

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
            fn (array $data): View => view('analytics::livewire.dashboard.marketing-campaign-detail', $data)
                ->layout($this->layoutName('marketing'), ['title' => $this->campaign->name.' · '.__('Marketing')]),
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
