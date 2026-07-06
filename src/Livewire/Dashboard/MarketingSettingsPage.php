<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Livewire\Dashboard\Concerns\RecoversFromReadFailure;
use Falcon\Analytics\Livewire\Dashboard\Concerns\ResolvesDashboardLayout;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\AdObjective;
use Falcon\Analytics\Models\Campaign;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Manages the marketing definitions: campaigns, their ads, and each ad's
 * conversion objectives. A campaign or ad is identified by free URL-parameter
 * conditions (all must hold, AND); objectives come from the declared funnels and
 * tracked events.
 */
final class MarketingSettingsPage extends Component
{
    use RecoversFromReadFailure;
    use ResolvesDashboardLayout;

    /** '' | campaign | ad | delete */
    public string $modal = '';

    public ?int $campaignId = null;

    public string $campaignName = '';

    public string $campaignPlatform = '';

    /** @var list<array{param: string, value: string}> */
    public array $campaignConditions = [];

    public ?int $adId = null;

    public ?int $adCampaignId = null;

    public string $adName = '';

    /** @var list<array{param: string, value: string}> */
    public array $adConditions = [];

    public string $objType = '';

    public string $objReference = '';

    public string $objValue = '';

    public string $deleteType = '';

    public ?int $deleteId = null;

    public string $deleteLabel = '';

    public function newCampaign(): void
    {
        $this->resetCampaignForm();
        $this->campaignConditions = [['param' => '', 'value' => '']];
        $this->modal = 'campaign';
    }

    public function editCampaign(int $id): void
    {
        $campaign = Campaign::findOrFail($id);

        $this->campaignId = $campaign->id;
        $this->campaignName = $campaign->name;
        $this->campaignPlatform = (string) $campaign->platform;
        $this->campaignConditions = $campaign->match_conditions ?: [['param' => '', 'value' => '']];
        $this->modal = 'campaign';
    }

    public function addCampaignCondition(): void
    {
        $this->campaignConditions[] = ['param' => '', 'value' => ''];
    }

    public function removeCampaignCondition(int $index): void
    {
        unset($this->campaignConditions[$index]);
        $this->campaignConditions = array_values($this->campaignConditions);
    }

    public function saveCampaign(): void
    {
        $this->validate($this->conditionRules('campaignConditions') + [
            'campaignName' => ['required', 'string', 'max:150'],
            'campaignPlatform' => ['nullable', 'string', 'max:60'],
        ], attributes: $this->conditionAttributes('campaignConditions') + ['campaignName' => __('nom')]);

        $campaign = $this->campaignId !== null ? Campaign::findOrFail($this->campaignId) : new Campaign;
        $campaign->fill([
            'name' => $this->campaignName,
            'platform' => $this->campaignPlatform !== '' ? $this->campaignPlatform : null,
            'match_conditions' => $this->cleanConditions($this->campaignConditions),
        ])->save();

        $this->modal = '';
        $this->resetCampaignForm();
    }

    public function newAd(int $campaignId): void
    {
        $this->resetAdForm();
        $this->adCampaignId = $campaignId;
        $this->adConditions = [['param' => '', 'value' => '']];
        $this->modal = 'ad';
    }

    public function editAd(int $id): void
    {
        $ad = Ad::findOrFail($id);

        $this->adId = $ad->id;
        $this->adCampaignId = $ad->campaign_id;
        $this->adName = $ad->name;
        $this->adConditions = $ad->match_conditions ?: [['param' => '', 'value' => '']];
        $this->modal = 'ad';
    }

    public function addAdCondition(): void
    {
        $this->adConditions[] = ['param' => '', 'value' => ''];
    }

    public function removeAdCondition(int $index): void
    {
        unset($this->adConditions[$index]);
        $this->adConditions = array_values($this->adConditions);
    }

    public function saveAd(): void
    {
        $this->validate($this->conditionRules('adConditions') + [
            'adName' => ['required', 'string', 'max:150'],
        ], attributes: $this->conditionAttributes('adConditions') + ['adName' => __('nom')]);

        $ad = $this->adId !== null ? Ad::findOrFail($this->adId) : new Ad;
        $ad->fill([
            'campaign_id' => $this->adCampaignId,
            'name' => $this->adName,
            'match_conditions' => $this->cleanConditions($this->adConditions),
        ])->save();

        // Stay in edit mode so objectives can be added straight away.
        $this->adId = $ad->id;
    }

    public function selectObjective(string $type, string $reference, ?float $value = null): void
    {
        $this->objType = $type;
        $this->objReference = $reference;
        $this->objValue = $type === 'event' && $value !== null ? (string) $value : '';
        $this->resetErrorBag(['objReference', 'objValue']);
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
        ], attributes: ['objReference' => __('objectif'), 'objValue' => __('valeur')]);

        AdObjective::updateOrCreate(
            ['ad_id' => $this->adId, 'type' => $this->objType, 'reference' => $this->objReference],
            ['value' => $this->objType === 'event' ? $this->objValue : null],
        );

        $this->objType = '';
        $this->objReference = '';
        $this->objValue = '';
    }

    public function removeObjective(int $id): void
    {
        AdObjective::query()->whereKey($id)->where('ad_id', $this->adId)->delete();
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
        $this->reset('deleteType', 'deleteId', 'deleteLabel');
    }

    private function resetCampaignForm(): void
    {
        $this->reset('campaignId', 'campaignName', 'campaignPlatform', 'campaignConditions');
    }

    private function resetAdForm(): void
    {
        $this->reset('adId', 'adCampaignId', 'adName', 'adConditions', 'objType', 'objReference', 'objValue');
    }

    /**
     * @return array<string, list<string>>
     */
    private function conditionRules(string $property): array
    {
        return [
            $property => ['required', 'array', 'min:1'],
            $property.'.*.param' => ['required', 'string', 'max:100'],
            $property.'.*.value' => ['required', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function conditionAttributes(string $property): array
    {
        return [
            $property.'.*.param' => __('paramètre'),
            $property.'.*.value' => __('valeur'),
        ];
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

    public function render(FunnelRegistry $funnels, EventRegistry $events): View
    {
        return $this->guardedRender(
            function () use ($funnels, $events): array {
                return [
                    'campaigns' => Campaign::query()->with(['ads.objectives'])->orderBy('name')->get(),
                    'editingAd' => $this->adId !== null ? Ad::query()->with('objectives')->find($this->adId) : null,
                    'funnelOptions' => array_map(
                        fn ($funnel): array => ['type' => 'funnel', 'reference' => $funnel->key, 'label' => $funnel->label, 'value' => null],
                        $funnels->all(),
                    ),
                    'eventOptions' => array_map(
                        fn ($event): array => ['type' => 'event', 'reference' => $event->name, 'label' => $event->label, 'value' => $event->value],
                        $events->all(),
                    ),
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.marketing-settings', $data)
                ->layout($this->layoutName(), ['title' => __('Marketing').' · '.__('Analytics')]),
        );
    }
}
