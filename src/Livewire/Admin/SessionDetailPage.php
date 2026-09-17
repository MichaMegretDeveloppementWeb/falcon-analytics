<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin;

use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Livewire\Admin\Concerns\RecoversFromReadFailure;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Services\Dashboard\SessionJourneyBuilder;
use Falcon\Analytics\Services\Dashboard\SessionSubjectAttributor;
use Falcon\Analytics\Services\SubjectResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
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
                    'detailErased' => $this->detailWasErased($events),
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
     * Whether this session had a step-by-step and has lost part of it.
     *
     * **It asks the session itself, and nothing else.** The counters were kept
     * as the visit happened; the rows are what is left of it. More counted than
     * kept means something was erased, and the two being equal means nothing
     * was — there is no third case, and no date to reason about.
     *
     * **The archive register was asked first, and it answered for a DAY.** That
     * is one question too wide, and it said yes in three situations where
     * nothing had been lost · a session that recorded nothing, on a day a busy
     * neighbour had emptied ; a session whose pages all sit on a route a
     * declared funnel protects, which the erasing spares ; and a session whose
     * every click carries a name, which the erasing spares too — the ordinary
     * shape of a visit on a site that declares its conversions. All three would
     * have read « le détail a été effacé » above a step-by-step that was whole.
     *
     * Nothing is queried for it · the rows are already loaded to draw the
     * journey.
     *
     * @param  Collection<int, Event>  $events
     */
    private function detailWasErased(Collection $events): bool
    {
        $recorded = $this->session->pageview_count + $this->session->click_count;

        $kept = $events
            ->filter(fn (Event $event): bool => $event->type === EventType::Pageview || $event->type === EventType::Click)
            ->count();

        return $recorded > $kept;
    }
}
