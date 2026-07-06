<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Livewire\Dashboard\Concerns\RecoversFromReadFailure;
use Falcon\Analytics\Livewire\Dashboard\Concerns\ResolvesDashboardLayout;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Services\SubjectResolver;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * A single visitor in detail: their identity, headline figures and the list of
 * their sessions (each linking to the session detail). Read-only.
 */
final class VisitorDetailPage extends Component
{
    use RecoversFromReadFailure;
    use ResolvesDashboardLayout;

    public Visitor $visitor;

    public function mount(Visitor $visitor): void
    {
        $this->visitor = $visitor;
    }

    public function render(SubjectResolver $subjects): View
    {
        return $this->guardedRender(
            function () use ($subjects): array {
                $sessions = $this->visitor->sessions()
                    ->select(['id', 'visitor_id', 'started_at', 'last_activity_at', 'pageview_count', 'device_type', 'browser', 'source', 'landing_route', 'landing_url', 'country', 'city'])
                    ->orderByDesc('started_at')
                    ->get();

                $subjectType = $this->visitor->subject_type;

                return [
                    'visitor' => $this->visitor,
                    'sessions' => $sessions,
                    'totalPageviews' => (int) $sessions->sum('pageview_count'),
                    'subjectLabel' => $subjectType !== null ? $subjects->label($subjectType) : null,
                    'subjectName' => $subjectType !== null ? $subjects->name($subjectType, (int) $this->visitor->subject_id) : null,
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.visitor-detail', $data)
                ->layout($this->layoutName(), ['title' => __('Visiteur').' · '.__('Analytics')]),
        );
    }
}
