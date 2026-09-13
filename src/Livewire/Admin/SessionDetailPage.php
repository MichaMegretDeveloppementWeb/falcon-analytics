<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin;

use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Livewire\Admin\Concerns\RecoversFromReadFailure;
use Falcon\Analytics\Models\DailyArchive;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Services\Dashboard\SessionJourneyBuilder;
use Falcon\Analytics\Services\Dashboard\SessionSubjectAttributor;
use Falcon\Analytics\Services\SubjectResolver;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * A single session in detail: its main information and the chronological journey
 * (pages visited, clicks nested under their page, time spent). Read-only.
 *
 * @internal
 */
final class SessionDetailPage extends Component
{
    use RecoversFromReadFailure;

    public Session $session;

    public function mount(Session $session): void
    {
        $this->session = $session->load('visitor:id,uuid,subject_type,subject_id,session_count,first_seen_at,last_seen_at');
    }

    public function render(SubjectResolver $subjects, SessionSubjectAttributor $attributor, SessionJourneyBuilder $journeys, EventRegistry $eventRegistry): View
    {
        return $this->guardedRender(
            function () use ($subjects, $attributor, $journeys, $eventRegistry): array {
                $events = $this->session->events()
                    ->orderBy('occurred_at')
                    ->orderBy('id')
                    ->get();

                $journey = $journeys->build($events, $this->session->started_at, $this->session->last_activity_at);
                $attribution = $attributor->attribute([$this->session])[$this->session->id] ?? null;

                $conversionNames = [];
                foreach ($eventRegistry->all() as $declared) {
                    if ($declared->isConversion()) {
                        $conversionNames[] = $declared->name;
                    }
                }

                return [
                    'session' => $this->session,
                    'journey' => $journey,

                    /*
                     * Read off the session, not by counting rows · past the
                     * retention its anonymous clicks are erased, and counting
                     * what is left would answer zero for a session that had
                     * four. The counter was kept as it happened, so it outlives
                     * the rows it counted.
                     *
                     * The two below still count rows, and that is right: named
                     * events are never erased, so their number is always
                     * readable from the rows themselves.
                     */
                    'clicksCount' => $this->session->click_count,

                    'eventsCount' => $events->whereNotNull('name')->count(),
                    'conversionsCount' => $events->whereIn('name', $conversionNames)->count(),
                    'detailErased' => $this->detailWasErased(),
                    'timePerPage' => $journeys->timePerPage($journey),
                    'subjectLabel' => $attribution !== null ? $subjects->label($attribution->guard) : null,
                    'subjectName' => $attribution !== null ? $subjects->name($attribution->guard, $attribution->id) : null,
                    'subjectId' => $attribution?->id,
                    'subjectViaVisitor' => $attribution !== null && $attribution->viaVisitor,
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.session-detail', $data),
        );
    }

    /**
     * Whether this session had a step-by-step and has lost it.
     *
     * **Two questions, and both are needed.**
     *
     * The day's, asked of the archive register rather than worked out from the
     * retention · the two part company as soon as a scheduler stops — days past
     * the retention and still intact — or as soon as a retention is shortened,
     * which moves a line the erasing has not crossed yet.
     *
     * And the session's own, because the register answers for a DAY. A session
     * that recorded nothing, on a day a busy neighbour had emptied, would
     * otherwise be told it lost something it never had. Its counters settle it:
     * no pages, no clicks, nothing to have lost.
     *
     * Named events are left out of the count on purpose · they are never
     * erased, so a session holding only those has lost nothing either.
     */
    private function detailWasErased(): bool
    {
        if ($this->session->pageview_count === 0 && $this->session->click_count === 0) {
            return false;
        }

        return DailyArchive::query()
            ->where('day', $this->session->started_at->toDateString())
            ->whereNotNull('pruned_at')
            ->exists();
    }
}
