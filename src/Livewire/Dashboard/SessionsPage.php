<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Livewire\Dashboard\Concerns\ResolvesSubjectNames;
use Falcon\Analytics\Livewire\Dashboard\Concerns\SortsAndSearchesList;
use Falcon\Analytics\Repositories\Dashboard\SessionListReadRepository;
use Falcon\Analytics\Services\Dashboard\SessionSubjectAttributor;
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

    public function render(
        SessionListReadRepository $sessionsRepository,
        SubjectResolver $subjects,
        SessionSubjectAttributor $attributor,
        EventRegistry $events,
    ): View {
        return $this->guardedRender(
            function () use ($sessionsRepository, $subjects, $attributor, $events): array {
                $period = $this->currentPeriod();
                $subjectType = $this->subjectType();

                $conversionNames = [];
                foreach ($events->all() as $event) {
                    if ($event->isConversion()) {
                        $conversionNames[] = $event->name;
                    }
                }

                $sessions = $sessionsRepository->paginateSessions($period, $subjectType, $this->search, $this->device ?: null, $this->source ?: null, $subjects, $this->sort, $this->direction, conversionNames: $conversionNames);
                $attributions = $attributor->attribute($sessions->items());

                return [
                    'range' => $period,
                    'sessions' => $sessions,
                    'attributions' => $attributions,
                    'subjectNames' => $this->resolveAttributedNames($attributions, $subjects),
                    'sort' => $this->sort,
                    'direction' => $this->direction,
                    'filterOptions' => $sessionsRepository->sessionFilterOptions($period, $subjectType),
                    ...$this->filterData(),
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.sessions', $data)
                ->layout($this->layoutName(), ['title' => __('Sessions').' · '.__('Analytics')]),
        );
    }
}
