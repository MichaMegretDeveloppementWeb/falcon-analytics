<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Livewire\Dashboard\Concerns\RecoversFromReadFailure;
use Falcon\Analytics\Livewire\Dashboard\Concerns\ResolvesDashboardLayout;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\AdObjective;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Manages the marketing definitions: campaigns, their ads, and each ad's
 * conversion objectives (a funnel, or named events with a value). The raw
 * campaign/ad values captured on sessions are matched to these at report time.
 */
final class MarketingSettingsPage extends Component
{
    use RecoversFromReadFailure;
    use ResolvesDashboardLayout;

    /** '' | campaign | ad | delete */
    public string $modal = '';

    public ?int $campaignId = null;

    public string $campaignName = '';

    public string $campaignKey = '';

    public string $campaignPlatform = '';

    public ?int $adCampaignId = null;

    public ?int $adId = null;

    public string $adName = '';

    public string $adKey = '';

    public string $objType = 'funnel';

    public string $objReference = '';

    public string $objValue = '';

    public string $deleteType = '';

    public ?int $deleteId = null;

    public string $deleteLabel = '';

    public function newCampaign(): void
    {
        $this->resetCampaignForm();
        $this->modal = 'campaign';
    }

    public function editCampaign(int $id): void
    {
        $campaign = Campaign::findOrFail($id);

        $this->campaignId = $campaign->id;
        $this->campaignName = $campaign->name;
        $this->campaignKey = $campaign->key;
        $this->campaignPlatform = (string) $campaign->platform;
        $this->modal = 'campaign';
    }

    public function saveCampaign(): void
    {
        $this->validate([
            'campaignName' => ['required', 'string', 'max:150'],
            'campaignKey' => ['required', 'string', 'max:150', Rule::unique('falcon_analytics_campaigns', 'key')->ignore($this->campaignId)],
            'campaignPlatform' => ['nullable', 'string', 'max:60'],
        ], attributes: [
            'campaignName' => __('nom'),
            'campaignKey' => __('identifiant'),
            'campaignPlatform' => __('plateforme'),
        ]);

        $campaign = $this->campaignId !== null ? Campaign::findOrFail($this->campaignId) : new Campaign;
        $campaign->fill([
            'name' => $this->campaignName,
            'key' => $this->campaignKey,
            'platform' => $this->campaignPlatform !== '' ? $this->campaignPlatform : null,
        ])->save();

        $this->modal = '';
        $this->resetCampaignForm();
    }

    public function newAd(int $campaignId): void
    {
        $this->resetAdForm();
        $this->adCampaignId = $campaignId;
        $this->modal = 'ad';
    }

    public function editAd(int $id): void
    {
        $ad = Ad::findOrFail($id);

        $this->adId = $ad->id;
        $this->adCampaignId = $ad->campaign_id;
        $this->adName = $ad->name;
        $this->adKey = $ad->key;
        $this->modal = 'ad';
    }

    public function saveAd(): void
    {
        $this->validate([
            'adName' => ['required', 'string', 'max:150'],
            'adKey' => ['required', 'string', 'max:150', Rule::unique('falcon_analytics_ads', 'key')->where('campaign_id', $this->adCampaignId)->ignore($this->adId)],
        ], attributes: [
            'adName' => __('nom'),
            'adKey' => __('identifiant'),
        ]);

        $ad = $this->adId !== null ? Ad::findOrFail($this->adId) : new Ad;
        $ad->fill([
            'campaign_id' => $this->adCampaignId,
            'name' => $this->adName,
            'key' => $this->adKey,
        ])->save();

        // Stay in edit mode so objectives can be added straight away.
        $this->adId = $ad->id;
    }

    public function addObjective(): void
    {
        if ($this->adId === null) {
            return;
        }

        $this->validate([
            'objType' => ['required', Rule::in(['funnel', 'event'])],
            'objReference' => ['required', 'string', 'max:191'],
            'objValue' => [Rule::requiredIf($this->objType === 'event'), 'nullable', 'numeric', 'min:0'],
        ], attributes: [
            'objReference' => __('objectif'),
            'objValue' => __('valeur'),
        ]);

        AdObjective::updateOrCreate(
            ['ad_id' => $this->adId, 'type' => $this->objType, 'reference' => $this->objReference],
            ['value' => $this->objType === 'event' ? $this->objValue : null],
        );

        $this->reset('objReference', 'objValue');
        $this->objType = 'funnel';
    }

    public function removeObjective(int $id): void
    {
        AdObjective::query()
            ->whereKey($id)
            ->where('ad_id', $this->adId)
            ->delete();
    }

    public function confirmDelete(string $type, int $id): void
    {
        $this->deleteType = $type;
        $this->deleteId = $id;
        $this->deleteLabel = $type === 'campaign'
            ? (string) Campaign::query()->whereKey($id)->value('name')
            : (string) Ad::query()->whereKey($id)->value('name');
        $this->modal = 'delete';
    }

    public function deleteConfirmed(): void
    {
        if ($this->deleteType === 'campaign' && $this->deleteId !== null) {
            $campaignId = $this->deleteId;

            DB::transaction(function () use ($campaignId): void {
                $adIds = Ad::query()->where('campaign_id', $campaignId)->pluck('id');
                AdObjective::query()->whereIn('ad_id', $adIds)->delete();
                Ad::query()->where('campaign_id', $campaignId)->delete();
                Campaign::query()->whereKey($campaignId)->delete();
            });
        }

        if ($this->deleteType === 'ad' && $this->deleteId !== null) {
            $adId = $this->deleteId;

            DB::transaction(function () use ($adId): void {
                AdObjective::query()->where('ad_id', $adId)->delete();
                Ad::query()->whereKey($adId)->delete();
            });
        }

        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->modal = '';
        $this->resetCampaignForm();
        $this->resetAdForm();
        $this->deleteType = '';
        $this->deleteId = null;
        $this->deleteLabel = '';
    }

    private function resetCampaignForm(): void
    {
        $this->reset('campaignId', 'campaignName', 'campaignKey', 'campaignPlatform');
    }

    private function resetAdForm(): void
    {
        $this->reset('adCampaignId', 'adId', 'adName', 'adKey', 'objType', 'objReference', 'objValue');
    }

    public function render(FunnelRegistry $funnels): View
    {
        return $this->guardedRender(
            function () use ($funnels): array {
                $campaigns = Campaign::query()->with(['ads.objectives'])->orderBy('name')->get();

                return [
                    'campaigns' => $campaigns,
                    'editingAd' => $this->adId !== null ? Ad::query()->with('objectives')->find($this->adId) : null,
                    'funnels' => $funnels->all(),
                    'observedEvents' => Event::query()
                        ->whereNotNull('name')
                        ->distinct()
                        ->orderBy('name')
                        ->limit(200)
                        ->pluck('name')
                        ->all(),
                    'undefinedCampaigns' => $this->undefinedCampaignValues($campaigns),
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.marketing-settings', $data)
                ->layout($this->layoutName(), ['title' => __('Publicités').' · '.__('Analytics')]),
        );
    }

    /**
     * Campaign values seen in real traffic that aren't defined yet, to nudge the
     * user into naming them.
     *
     * @param  Collection<int, Campaign>  $campaigns
     * @return list<string>
     */
    private function undefinedCampaignValues($campaigns): array
    {
        $defined = $campaigns->pluck('key')->all();

        return Session::query()
            ->whereNotNull('mkt_campaign')
            ->when($defined !== [], fn ($query) => $query->whereNotIn('mkt_campaign', $defined))
            ->distinct()
            ->orderBy('mkt_campaign')
            ->limit(50)
            ->pluck('mkt_campaign')
            ->all();
    }
}
