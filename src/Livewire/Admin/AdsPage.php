<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin;

use Falcon\Analytics\DTOs\Dashboard\Marketing\AdRow;
use Falcon\Analytics\Enums\Authorization\Ability;
use Falcon\Analytics\Livewire\Admin\Concerns\AsksTheScreenAbility;
use Falcon\Analytics\Livewire\Admin\Concerns\RecoversFromReadFailure;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Services\Dashboard\ObjectiveLabels;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * A flat, searchable table of every ad across all campaigns. An ad and its
 * objectives are edited on its campaign's detail page or on its own.
 *
 * @internal
 */
final class AdsPage extends Component
{
    use AsksTheScreenAbility;
    use RecoversFromReadFailure;
    use WithPagination;

    private const PER_PAGE = 20;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(ObjectiveLabels $objectives): View
    {
        return $this->guardedRender(
            function () use ($objectives): array {
                $ads = Ad::query()
                    ->with(['campaign:id,name', 'objectives:id,ad_id,type,reference'])
                    ->when($this->search !== '', function (Builder $query): void {
                        $term = '%'.$this->search.'%';
                        $query->where('name', 'like', $term)
                            ->orWhereHas('campaign', fn (Builder $campaign): Builder => $campaign->where('name', 'like', $term));
                    })
                    ->orderBy('name')
                    // Two ads can share a name: the key makes the paginated order total.
                    ->orderBy('id')
                    ->paginate(self::PER_PAGE);

                return [
                    'ads' => $ads->through(fn (Ad $ad): AdRow => AdRow::of($ad, $ad->campaign->name, $objectives->tagsOf($ad->objectives))),
                    'total' => Ad::query()->count(),
                    'mayOpenCampaigns' => Gate::allows(Ability::Campaigns),
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.marketing-ads', $data),
        );
    }

    protected function screenAbility(): Ability
    {
        return Ability::Ads;
    }
}
