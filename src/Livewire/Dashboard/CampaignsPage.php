<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Livewire\Dashboard\Concerns\RecoversFromReadFailure;
use Falcon\Analytics\Livewire\Dashboard\Concerns\ResolvesDashboardLayout;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\AdObjective;
use Falcon\Analytics\Models\Campaign;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The campaigns management list: a searchable, paginated table of campaigns with
 * their URL conditions and ad count. Campaigns are created and edited here; ads
 * and objectives are managed from a campaign's detail page.
 */
final class CampaignsPage extends Component
{
    use RecoversFromReadFailure;
    use ResolvesDashboardLayout;
    use WithPagination;

    private const PER_PAGE = 20;

    public string $search = '';

    /** '' | campaign | delete */
    public string $modal = '';

    public ?int $campaignId = null;

    public string $campaignName = '';

    public string $campaignPlatform = '';

    /** @var list<array{param: string, value: string}> */
    public array $campaignConditions = [];

    public ?int $deleteId = null;

    public string $deleteLabel = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function newCampaign(): void
    {
        $this->reset('campaignId', 'campaignName', 'campaignPlatform');
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

    public function addCondition(): void
    {
        $this->campaignConditions[] = ['param' => '', 'value' => ''];
    }

    public function removeCondition(int $index): void
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

        $campaign = $this->campaignId !== null ? Campaign::findOrFail($this->campaignId) : new Campaign;
        $campaign->fill([
            'name' => $this->campaignName,
            'platform' => $this->campaignPlatform !== '' ? $this->campaignPlatform : null,
            'match_conditions' => $this->cleanConditions($this->campaignConditions),
        ])->save();

        $this->closeModal();
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->deleteLabel = (string) Campaign::query()->whereKey($id)->value('name');
        $this->modal = 'delete';
    }

    public function deleteConfirmed(): void
    {
        if ($this->deleteId !== null) {
            $campaignId = $this->deleteId;

            DB::transaction(function () use ($campaignId): void {
                $adIds = Ad::query()->where('campaign_id', $campaignId)->pluck('id');
                AdObjective::query()->whereIn('ad_id', $adIds)->delete();
                Ad::query()->where('campaign_id', $campaignId)->delete();
                Campaign::query()->whereKey($campaignId)->delete();
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

    public function render(): View
    {
        return $this->guardedRender(
            function (): array {
                $campaigns = Campaign::query()
                    ->withCount('ads')
                    ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
                    ->orderBy('name')
                    ->paginate(self::PER_PAGE);

                return [
                    'campaigns' => $campaigns,
                    'total' => Campaign::query()->count(),
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.marketing-campaigns', $data)
                ->layout($this->layoutName('marketing'), ['title' => __('Campagnes').' · '.__('Marketing')]),
        );
    }
}
