<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Repositories\DashboardReadRepository;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Session explorer: engagement stats for the period plus a paginated, filterable
 * list of every non-bot session, each row linking to its detail.
 */
final class SessionsPage extends DashboardComponent
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $device = '';

    #[Url]
    public string $source = '';

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

    public function updatedDevice(): void
    {
        $this->resetPage();
    }

    public function updatedSource(): void
    {
        $this->resetPage();
    }

    public function render(DashboardReadRepository $repository): View
    {
        $period = $this->currentPeriod();
        $subjectType = $this->subjectType();

        return view('analytics::livewire.dashboard.sessions', [
            'range' => $period,
            'headline' => $repository->headline($period, $subjectType),
            'sparklines' => $repository->headlineSparklines($period, $subjectType),
            'sessions' => $repository->paginateSessions($period, $subjectType, $this->search, $this->device ?: null, $this->source ?: null),
            'filterOptions' => $repository->sessionFilterOptions($period, $subjectType),
            ...$this->filterData(),
        ])->layout($this->layoutName(), ['title' => __('Sessions').' · '.__('Analytics')]);
    }
}
