<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Actions\DeleteCampaignAction;
use Falcon\Analytics\Livewire\Dashboard\Concerns\EditsCampaign;
use Falcon\Analytics\Livewire\Dashboard\Concerns\RecoversFromReadFailure;
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
    use EditsCampaign;
    use RecoversFromReadFailure;
    use WithPagination;

    private const PER_PAGE = 20;

    public string $search = '';

    /** '' | campaign | delete */
    public string $modal = '';

    public ?int $deleteId = null;

    public string $deleteLabel = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    protected function campaignFormId(): ?int
    {
        return $this->campaignId;
    }

    public function newCampaign(): void
    {
        $this->blankCampaignForm();
        $this->modal = 'campaign';
    }

    public function editCampaign(int $id): void
    {
        try {
            $campaign = Campaign::query()->findOrFail($id);
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Campaign.edit_load_failed', [
                'campaign_id' => $id,
                'exception' => $e,
            ]);
            $this->dispatch('toast', type: 'danger', title: __('Cette campagne est introuvable. Actualisez la page.'));

            return;
        }

        $this->fillCampaignForm($campaign);
        $this->modal = 'campaign';
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
            fn (array $data): View => view('analytics::livewire.dashboard.marketing-campaigns', $data),
        );
    }
}
