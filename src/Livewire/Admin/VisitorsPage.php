<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin;

use Falcon\Analytics\Enums\Authorization\Ability;
use Falcon\Analytics\Livewire\Admin\Concerns\AsksTheScreenAbility;
use Falcon\Analytics\Livewire\Admin\Concerns\SortsAndSearchesList;
use Falcon\Analytics\Repositories\Dashboard\VisitorListReadRepository;
use Falcon\Analytics\Services\Dashboard\VisitorRowBuilder;
use Falcon\Analytics\Services\SubjectResolver;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Visitor directory: period-scoped headline metrics on top, then the all-time
 * list of every visitor profile, each row summarising their sessions, first and
 * last visit, locality and acquisition source. The period filter drives ONLY
 * the headline: the directory reflects the whole population.
 *
 * @internal
 */
final class VisitorsPage extends DashboardComponent
{
    use AsksTheScreenAbility;
    use SortsAndSearchesList;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sort = 'last_seen_at';

    #[Url]
    public string $direction = 'desc';

    public function render(VisitorListReadRepository $repository, SubjectResolver $subjects, VisitorRowBuilder $rows): View
    {
        return $this->guardedRender(
            function () use ($repository, $subjects, $rows): array {
                $visitors = $repository->paginateVisitors($this->subjectType(), $this->search, $subjects, $this->sort, $this->direction);

                return [
                    'range' => $this->currentPeriod(),
                    'visitors' => $rows->build($visitors),
                    'sort' => $this->sort,
                    'direction' => $this->direction,
                    ...$this->filterData(),
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.visitors', $data),
        );
    }

    protected function screenAbility(): Ability
    {
        return Ability::Visitors;
    }
}
