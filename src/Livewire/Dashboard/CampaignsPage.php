<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Actions\DeleteCampaignAction;
use Falcon\Analytics\Actions\SaveCampaignAction;
use Falcon\Analytics\Livewire\Dashboard\Concerns\RecoversFromReadFailure;
use Falcon\Analytics\Livewire\Dashboard\Concerns\ResolvesDashboardLayout;
use Falcon\Analytics\Models\Campaign;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

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

    public function saveCampaign(SaveCampaignAction $action): void
    {
        $this->validate([
            'campaignName' => ['required', 'string', 'max:150'],
            'campaignPlatform' => ['nullable', 'string', 'max:60'],
            'campaignConditions' => ['required', 'array', 'min:1'],
            'campaignConditions.*.param' => ['required', 'string', 'max:100'],
            'campaignConditions.*.value' => ['required', 'string', 'max:150'],
        ], messages: [
            'campaignName.required' => __('Le nom est obligatoire.'),
            'campaignName.max' => __('Le nom ne doit pas dépasser :max caractères.'),
            'campaignConditions.required' => __('Ajoutez au moins une condition.'),
            'campaignConditions.min' => __('Ajoutez au moins une condition.'),
            'campaignConditions.*.param.required' => __('Le paramètre est obligatoire.'),
            'campaignConditions.*.value.required' => __('La valeur est obligatoire.'),
        ], attributes: [
            'campaignName' => __('nom'),
            'campaignConditions.*.param' => __('paramètre'),
            'campaignConditions.*.value' => __('valeur'),
        ]);

        try {
            $action->execute(
                $this->campaignId,
                $this->campaignName,
                $this->campaignPlatform !== '' ? $this->campaignPlatform : null,
                $this->cleanConditions($this->campaignConditions),
            );
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Campaign.save_failed', [
                'campaign_id' => $this->campaignId,
                'exception' => $e,
            ]);
            $this->dispatch('toast', type: 'danger', title: __('L\'enregistrement de la campagne a échoué. Réessayez.'));

            return;
        }

        $this->closeModal();
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->deleteLabel = (string) Campaign::query()->whereKey($id)->value('name');
        $this->modal = 'delete';
    }

    public function deleteConfirmed(DeleteCampaignAction $action): void
    {
        if ($this->deleteId !== null) {
            try {
                $action->execute($this->deleteId);
            } catch (Throwable $e) {
                Log::channel(config('analytics.log_channel'))->error('Campaign.delete_failed', [
                    'campaign_id' => $this->deleteId,
                    'exception' => $e,
                ]);
                $this->dispatch('toast', type: 'danger', title: __('La suppression de la campagne a échoué. Réessayez.'));

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
