<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin;

use Falcon\Analytics\Actions\DeleteCampaignAction;
use Falcon\Analytics\Livewire\Admin\Concerns\EditsCampaign;
use Falcon\Analytics\Livewire\Admin\Concerns\RecoversFromReadFailure;
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
 *
 * @internal
 */
final class CampaignsPage extends Component
{
    use EditsCampaign;
    use RecoversFromReadFailure;
    use WithPagination;

    private const PER_PAGE = 20;

    public string $search = '';

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

    /** Opens a blank form · the modal opens on the answer. */
    public function newCampaign(): bool
    {
        $this->blankCampaignForm();

        return true;
    }

    /** Whether the campaign could be read into the form · the modal opens on a yes. */
    public function editCampaign(int $id): bool
    {
        try {
            $campaign = Campaign::query()->findOrFail($id);
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Campaign.edit_load_failed', [
                'campaign_id' => $id,
                'exception' => $e,
            ]);
            $this->dispatch('ui-toast', type: 'danger', title: __('Cette campagne est introuvable. Actualisez la page.'));

            return false;
        }

        $this->fillCampaignForm($campaign);

        return true;
    }

    /** Whether there is a campaign to ask about · the confirmation opens on a yes. */
    public function confirmDelete(int $id): bool
    {
        $name = Campaign::query()->whereKey($id)->value('name');

        if (! is_string($name)) {
            $this->dispatch('ui-toast', type: 'danger', title: __('Cette campagne est introuvable. Actualisez la page.'));

            return false;
        }

        $this->deleteId = $id;
        $this->deleteLabel = $name;

        return true;
    }

    /** Whether the campaign was deleted · the confirmation closes on a yes. */
    public function deleteConfirmed(DeleteCampaignAction $action): bool
    {
        if ($this->deleteId === null) {
            return false;
        }

        try {
            $action->execute($this->deleteId);
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Campaign.delete_failed', [
                'campaign_id' => $this->deleteId,
                'exception' => $e,
            ]);
            $this->dispatch('ui-toast', type: 'danger', title: __('La suppression de la campagne a échoué. Réessayez.'));

            return false;
        }

        return true;
    }

    public function render(): View
    {
        return $this->guardedRender(
            function (): array {
                $campaigns = Campaign::query()
                    ->withCount('ads')
                    ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
                    ->orderBy('name')
                    // Two campaigns can share a name: the key closes the order,
                    // which a paginated list needs to be total.
                    ->orderBy('id')
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
