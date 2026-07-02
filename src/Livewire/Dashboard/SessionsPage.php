<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Repositories\DashboardReadRepository;
use Falcon\Analytics\Services\SubjectResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

    public function render(DashboardReadRepository $repository, SubjectResolver $subjects): View
    {
        $period = $this->currentPeriod();
        $subjectType = $this->subjectType();
        $sessions = $repository->paginateSessions($period, $subjectType, $this->search, $this->device ?: null, $this->source ?: null);

        return view('analytics::livewire.dashboard.sessions', [
            'range' => $period,
            'headline' => $repository->headline($period, $subjectType),
            'sparklines' => $repository->headlineSparklines($period, $subjectType),
            'sessions' => $sessions,
            'subjectNames' => $this->resolveSubjectNames($sessions, $subjects),
            'filterOptions' => $repository->sessionFilterOptions($period, $subjectType),
            ...$this->filterData(),
        ])->layout($this->layoutName(), ['title' => __('Sessions').' · '.__('Analytics')]);
    }

    /**
     * Batch-resolve a "guard:id" => name map for the current page, one query per
     * guard, so the list never triggers per-row lookups.
     *
     * @param  LengthAwarePaginator<int, Session>  $sessions
     * @return array<string, string>
     */
    private function resolveSubjectNames(LengthAwarePaginator $sessions, SubjectResolver $subjects): array
    {
        $byGuard = [];
        foreach ($sessions as $session) {
            if ($session->subject_type !== null && $session->subject_id !== null) {
                $byGuard[$session->subject_type][] = (int) $session->subject_id;
            }
        }

        $names = [];
        foreach ($byGuard as $guard => $ids) {
            foreach ($subjects->names($guard, $ids) as $id => $name) {
                $names[$guard.':'.$id] = $name;
            }
        }

        return $names;
    }
}
