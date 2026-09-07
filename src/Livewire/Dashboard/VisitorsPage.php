<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Livewire\Dashboard\Concerns\ResolvesSubjectNames;
use Falcon\Analytics\Livewire\Dashboard\Concerns\SortsAndSearchesList;
use Falcon\Analytics\Repositories\Dashboard\VisitorListReadRepository;
use Falcon\Analytics\Services\SubjectResolver;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Visitor directory: period-scoped headline metrics on top, then the all-time
 * list of every visitor profile, each row summarising their sessions, first and
 * last visit, locality and acquisition source. The period filter deliberately
 * drives ONLY the headline: the directory reflects the general state.
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

    public function render(VisitorListReadRepository $repository, SubjectResolver $subjects): View
    {
        return $this->guardedRender(
            function () use ($repository, $subjects): array {
                $subjectType = $this->subjectType();

                $visitors = $repository->paginateVisitors($subjectType, $this->search, $subjects, $this->sort, $this->direction);

                return [
                    'range' => $this->currentPeriod(),
                    'visitors' => $visitors,
                    'subjectNames' => $this->resolveSubjectNames($visitors, $subjects),
                    'sort' => $this->sort,
                    'direction' => $this->direction,
                    ...$this->filterData(),
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.visitors', $data),
        );
    }
}
