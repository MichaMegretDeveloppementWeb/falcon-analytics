<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Livewire\Dashboard\Concerns\RecoversFromReadFailure;
use Falcon\Analytics\Livewire\Dashboard\Concerns\ResolvesDashboardLayout;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Services\Dashboard\SessionJourneyBuilder;
use Falcon\Analytics\Services\Dashboard\SessionSubjectAttributor;
use Falcon\Analytics\Services\SubjectResolver;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * A single session in detail: its main information and the chronological journey
 * (pages visited, clicks nested under their page, time spent). Read-only.
 */
final class SessionDetailPage extends Component
{
    use RecoversFromReadFailure;
    use ResolvesDashboardLayout;

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
                    'clicksCount' => $events->where('type', EventType::Click)->count(),
                    'eventsCount' => $events->whereNotNull('name')->count(),
                    'conversionsCount' => $events->whereIn('name', $conversionNames)->count(),
                    'timePerPage' => $journeys->timePerPage($journey),
                    'subjectLabel' => $attribution !== null ? $subjects->label($attribution->guard) : null,
                    'subjectName' => $attribution !== null ? $subjects->name($attribution->guard, $attribution->id) : null,
                    'subjectId' => $attribution?->id,
                    'subjectViaVisitor' => $attribution !== null && $attribution->viaVisitor,
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.session-detail', $data)
                ->layout($this->layoutName(), ['title' => __('Session').' · '.__('Analytics')]),
        );
    }
}
