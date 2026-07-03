<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Livewire\Dashboard\Concerns\ResolvesSubjectNames;
use Falcon\Analytics\Livewire\Dashboard\Concerns\SortsAndSearchesList;
use Falcon\Analytics\Repositories\DashboardReadRepository;
use Falcon\Analytics\Services\Dashboard\VisitorMetricsCalculator;
use Falcon\Analytics\Services\SubjectResolver;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Visitor explorer: headline metrics for the period plus a paginated, filterable
 * list of visitors active in the range, each row summarising their sessions,
 * first and last visit, locality and acquisition source.
 */
final class VisitorsPage extends DashboardComponent
{
    use ResolvesSubjectNames;
    use SortsAndSearchesList;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sort = 'last_seen_at';

    #[Url]
    public string $direction = 'desc';

    public function render(DashboardReadRepository $repository, SubjectResolver $subjects, VisitorMetricsCalculator $metrics): View
    {
        $period = $this->currentPeriod();
        $subjectType = $this->subjectType();

        $visitors = $repository->paginateVisitors($period, $subjectType, $this->search, $subjects, $this->sort, $this->direction);

        return view('analytics::livewire.dashboard.visitors', [
            'range' => $period,
            'metrics' => $metrics->compute(
                $repository->visitorCounts($period, $subjectType),
                $repository->visitorCounts($period->previous(), $subjectType),
                $repository->visitorDailyRows($period, $subjectType),
                $period,
            ),
            'visitors' => $visitors,
            'subjectNames' => $this->resolveSubjectNames($visitors, $subjects),
            'sort' => $this->sort,
            'direction' => $this->direction,
            ...$this->filterData(),
        ])->layout($this->layoutName(), ['title' => __('Visiteurs').' · '.__('Analytics')]);
    }
}
