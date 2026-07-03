<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Livewire\Dashboard\Concerns\ResolvesSubjectNames;
use Falcon\Analytics\Livewire\Dashboard\Concerns\SortsAndSearchesList;
use Falcon\Analytics\Repositories\DashboardReadRepository;
use Falcon\Analytics\Services\Dashboard\EngagementMetricsCalculator;
use Falcon\Analytics\Services\SubjectResolver;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Session explorer: engagement stats for the period plus a paginated, filterable
 * list of every non-bot session, each row linking to its detail.
 */
final class SessionsPage extends DashboardComponent
{
    use ResolvesSubjectNames;
    use SortsAndSearchesList;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $device = '';

    #[Url]
    public string $source = '';

    #[Url]
    public string $sort = 'started_at';

    #[Url]
    public string $direction = 'desc';

    public function updatedDevice(): void
    {
        $this->resetPage();
    }

    public function updatedSource(): void
    {
        $this->resetPage();
    }

    public function render(DashboardReadRepository $repository, SubjectResolver $subjects, EngagementMetricsCalculator $engagement): View
    {
        $period = $this->currentPeriod();
        $subjectType = $this->subjectType();
        $sessions = $repository->paginateSessions($period, $subjectType, $this->search, $this->device ?: null, $this->source ?: null, $subjects, $this->sort, $this->direction);

        return view('analytics::livewire.dashboard.sessions', [
            'range' => $period,
            'headline' => $engagement->headline(
                $repository->headlineCounts($period, $subjectType),
                $repository->headlineCounts($period->previous(), $subjectType),
            ),
            'sparklines' => $engagement->sparklines($repository->sparklineRows($period, $subjectType), $period),
            'sessions' => $sessions,
            'subjectNames' => $this->resolveSubjectNames($sessions, $subjects),
            'sort' => $this->sort,
            'direction' => $this->direction,
            'filterOptions' => $repository->sessionFilterOptions($period, $subjectType),
            ...$this->filterData(),
        ])->layout($this->layoutName(), ['title' => __('Sessions').' · '.__('Analytics')]);
    }
}
