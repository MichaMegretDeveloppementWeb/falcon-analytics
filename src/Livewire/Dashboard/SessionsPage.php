<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Repositories\DashboardReadRepository;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Paginated session explorer: every non-bot session in the selected period,
 * newest first, searchable by IP or locality.
 */
final class SessionsPage extends DashboardComponent
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public function updatedPeriod(): void
    {
        $this->resetPage();
    }

    public function updatedSubject(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(DashboardReadRepository $repository): View
    {
        $period = $this->currentPeriod();

        return view('analytics::livewire.dashboard.sessions', [
            'sessions' => $repository->paginateSessions($period, $this->subjectType(), $this->search),
            ...$this->filterData(),
        ])->layout($this->layoutName(), ['title' => __('Sessions').' · '.__('Analytics')]);
    }
}
