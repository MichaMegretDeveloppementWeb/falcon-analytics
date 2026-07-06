<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Livewire\Dashboard\Concerns\RecoversFromReadFailure;
use Falcon\Analytics\Livewire\Dashboard\Concerns\ResolvesDashboardLayout;
use Falcon\Analytics\Models\Ad;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * A flat, searchable table of every ad across all campaigns. Editing an ad and
 * its objectives happens on the parent campaign's detail page.
 */
final class AdsPage extends Component
{
    use RecoversFromReadFailure;
    use ResolvesDashboardLayout;
    use WithPagination;

    private const PER_PAGE = 20;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(FunnelRegistry $funnels, EventRegistry $events): View
    {
        return $this->guardedRender(
            function () use ($funnels, $events): array {
                $ads = Ad::query()
                    ->with(['campaign', 'objectives'])
                    ->when($this->search !== '', function (Builder $query): void {
                        $term = '%'.$this->search.'%';
                        $query->where('name', 'like', $term)
                            ->orWhereHas('campaign', fn (Builder $campaign): Builder => $campaign->where('name', 'like', $term));
                    })
                    ->orderBy('name')
                    ->paginate(self::PER_PAGE);

                $labels = [];
                foreach ($funnels->all() as $funnel) {
                    $labels['funnel:'.$funnel->key] = $funnel->label;
                }
                foreach ($events->all() as $event) {
                    $labels['event:'.$event->name] = $event->label;
                }

                return [
                    'ads' => $ads,
                    'objectiveLabels' => $labels,
                    'total' => Ad::query()->count(),
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.marketing-ads', $data)
                ->layout($this->layoutName('marketing'), ['title' => __('Pubs').' · '.__('Marketing')]),
        );
    }
}
