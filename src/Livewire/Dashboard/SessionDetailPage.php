<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Livewire\Dashboard\Concerns\ResolvesDashboardLayout;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Services\Dashboard\SessionJourneyBuilder;
use Falcon\Analytics\Services\SubjectResolver;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * A single session in detail: its main information and the chronological journey
 * (pages visited, clicks nested under their page, time spent). Read-only.
 */
final class SessionDetailPage extends Component
{
    use ResolvesDashboardLayout;

    public Session $session;

    public function mount(Session $session): void
    {
        $this->session = $session->load('visitor:id,uuid,subject_type,subject_id,session_count,first_seen_at,last_seen_at');
    }

    public function render(SubjectResolver $subjects, SessionJourneyBuilder $journeys): View
    {
        $events = $this->session->events()
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $journey = $journeys->build($events, $this->session->started_at, $this->session->last_activity_at);
        $subjectType = $this->session->subject_type;

        return view('analytics::livewire.dashboard.session-detail', [
            'session' => $this->session,
            'journey' => $journey,
            'clicksCount' => $events->where('type', EventType::Click)->count(),
            'timePerPage' => $journeys->timePerPage($journey),
            'subjectLabel' => $subjectType !== null ? $subjects->label($subjectType) : null,
            'subjectName' => $subjectType !== null ? $subjects->name($subjectType, (int) $this->session->subject_id) : null,
        ])->layout($this->layoutName(), ['title' => __('Session').' · '.__('Analytics')]);
    }
}
