<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin;

use Falcon\Analytics\Actions\DeleteCampaignAction;
use Falcon\Analytics\DTOs\Dashboard\Marketing\CampaignRow;
use Falcon\Analytics\Enums\Authorization\Ability;
use Falcon\Analytics\Livewire\Admin\Concerns\AsksTheScreenAbility;
use Falcon\Analytics\Livewire\Admin\Concerns\RecoversFromReadFailure;
use Falcon\Analytics\Livewire\Admin\Concerns\SaysWhatTheAccountMayDo;
use Falcon\Analytics\Models\Campaign;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * The campaigns management list: a searchable, paginated table of campaigns with
 * their URL conditions and ad count. Campaigns are created and edited here, by
 * the form laid once under the list; ads and objectives are managed from a
 * campaign's detail page, and an ad can also be edited from its own.
 *
 * @internal
 */
final class CampaignsPage extends Component
{
    use AsksTheScreenAbility;
    use RecoversFromReadFailure;
    use SaysWhatTheAccountMayDo;
    use WithPagination;

    private const PER_PAGE = 20;

    public string $search = '';

    public ?int $deleteId = null;

    public string $deleteLabel = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /** Draws the list again once the form has written to it · the render reads it afresh. */
    #[On('an-campaigns-changed')]
    public function refresh(): void {}

    /** Whether there is a campaign to ask about · the confirmation opens on a yes. */
    public function confirmDelete(int $id): bool
    {
        $campaign = $this->campaignToDelete($id);

        if ($campaign === null) {
            return false;
        }

        $this->authorize(Ability::CampaignsDelete, $campaign);

        $this->deleteId = $campaign->id;
        $this->deleteLabel = $campaign->name;

        return true;
    }

    /** Whether the campaign was deleted · the confirmation closes on a yes. */
    public function deleteConfirmed(DeleteCampaignAction $action): bool
    {
        $campaign = $this->deleteId === null ? null : $this->campaignToDelete($this->deleteId);

        if ($campaign === null) {
            return false;
        }

        $this->authorize(Ability::CampaignsDelete, $campaign);

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
                    // Two campaigns can share a name: the key makes the paginated order total.
                    ->orderBy('id')
                    ->paginate(self::PER_PAGE);

                return [
                    'mayCreate' => Gate::allows(Ability::CampaignsEdit),
                    'mayEdit' => $this->allowedOn(Ability::CampaignsEdit, $campaigns->items()),
                    'mayDelete' => $this->allowedOn(Ability::CampaignsDelete, $campaigns->items()),
                    'campaigns' => $campaigns->through(CampaignRow::of(...)),
                    'total' => Campaign::query()->count(),
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.marketing-campaigns', $data),
        );
    }

    protected function screenAbility(): Ability
    {
        return Ability::Campaigns;
    }

    /** The campaign a deletion names, or null once told it no longer exists. */
    private function campaignToDelete(int $id): ?Campaign
    {
        $campaign = Campaign::query()->find($id);

        if ($campaign === null) {
            $this->dispatch('ui-toast', type: 'danger', title: __('Cette campagne est introuvable. Actualisez la page.'));
        }

        return $campaign;
    }
}
