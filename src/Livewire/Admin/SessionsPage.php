<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin;

use Falcon\Analytics\Enums\Authorization\Ability;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Livewire\Admin\Concerns\AsksTheScreenAbility;
use Falcon\Analytics\Livewire\Admin\Concerns\SortsAndSearchesList;
use Falcon\Analytics\Repositories\Dashboard\SessionListReadRepository;
use Falcon\Analytics\Services\Dashboard\SessionRowBuilder;
use Falcon\Analytics\Services\SubjectResolver;
use Falcon\Analytics\Support\DeviceLabel;
use Falcon\Analytics\Support\SourceLabel;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Session explorer: engagement stats for the period plus a paginated, filterable
 * list of every non-bot session, each row linking to its detail.
 *
 * @internal
 */
final class SessionsPage extends DashboardComponent
{
    use AsksTheScreenAbility;
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
        SessionRowBuilder $rows,
        EventRegistry $events,
    ): View {
        return $this->guardedRender(
            function () use ($sessionsRepository, $subjects, $rows, $events): array {
                $period = $this->currentPeriod();
                $subjectType = $this->subjectType();

                $conversionNames = [];
                foreach ($events->all() as $event) {
                    if ($event->isConversion()) {
                        $conversionNames[] = $event->name;
                    }
                }

                $device = $this->device === '' ? null : $this->device;
                $source = $this->source === '' ? null : $this->source;

                $sessions = $sessionsRepository->paginateSessions($period, $subjectType, $this->search, $device, $source, $subjects, $this->sort, $this->direction, conversionNames: $conversionNames);
                $filterOptions = $sessionsRepository->sessionFilterOptions($period, $subjectType);

                return [
                    'range' => $period,
                    'sessions' => $rows->build($sessions),
                    'sort' => $this->sort,
                    'direction' => $this->direction,
                    'deviceOptions' => $this->labelled($filterOptions['devices'], __('Tous les appareils'), DeviceLabel::for(...)),
                    'sourceOptions' => $this->labelled($filterOptions['sources'], __('Toutes les sources'), SourceLabel::for(...)),
                    ...$this->filterData(),
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.sessions', $data),
        );
    }

    /**
     * A filter's stored values turned into options, the label coming from the
     * class that every screen reads, so the same channel or device never shows
     * one wording in the list and another in the filter above it.
     *
     * @param  list<string>  $values
     * @param  callable(string): string  $label
     * @return array<string, string>
     */
    private function labelled(array $values, string $everything, callable $label): array
    {
        $options = ['' => $everything];

        foreach ($values as $value) {
            $options[$value] = $label($value);
        }

        return $options;
    }

    protected function screenAbility(): Ability
    {
        return Ability::Sessions;
    }
}
