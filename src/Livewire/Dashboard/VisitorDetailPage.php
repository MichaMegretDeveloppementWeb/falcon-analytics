<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Actions\ForgetVisitorAction;
use Falcon\Analytics\Livewire\Dashboard\Concerns\RecoversFromReadFailure;
use Falcon\Analytics\Livewire\Dashboard\Concerns\ResolvesDashboardLayout;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Services\Dashboard\VisitorEngagementCalculator;
use Falcon\Analytics\Services\SubjectResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * A single visitor in detail: their identity, headline figures and the list of
 * their sessions (each linking to the session detail). Read-only apart from the
 * GDPR erasure action.
 */
final class VisitorDetailPage extends Component
{
    use RecoversFromReadFailure;
    use ResolvesDashboardLayout;

    /** Upper bound on the sessions loaded for the profile, so a high-volume visitor never loads unbounded rows. */
    private const SESSIONS_LIMIT = 100;

    public Visitor $visitor;

    public function mount(Visitor $visitor): void
    {
        $this->visitor = $visitor;
    }

    /**
     * Erase this visitor's data (GDPR right to erasure) and return to the list.
     * Logged on the analytics channel for an audit trail; a failure degrades to
     * an inline error rather than a raw 500.
     */
    public function forget(ForgetVisitorAction $action): void
    {
        try {
            $action->execute($this->visitor);
        } catch (\Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Analytics visitor erasure failed.', [
                'visitor_id' => $this->visitor->id,
                'exception' => $e,
            ]);

            $this->addError('visitor-erasure-failed', __('La suppression a échoué. Réessayez dans un instant.'));

            return;
        }

        Log::channel(config('analytics.log_channel'))->info('Analytics visitor erased.', ['visitor_id' => $this->visitor->id]);

        $this->redirect(route(config('analytics.dashboard.route_name', 'analytics').'.visitors'));
    }

    public function render(SubjectResolver $subjects, VisitorEngagementCalculator $engagement): View
    {
        return $this->guardedRender(
            function () use ($subjects, $engagement): array {
                $sessions = $this->visitor->sessions()
                    ->select(['id', 'visitor_id', 'started_at', 'last_activity_at', 'pageview_count', 'device_type', 'browser', 'source', 'landing_route', 'landing_url', 'country', 'city'])
                    ->orderByDesc('started_at')
                    ->limit(self::SESSIONS_LIMIT)
                    ->get();

                $subjectType = $this->visitor->subject_type;

                return [
                    'visitor' => $this->visitor,
                    'sessions' => $sessions,
                    ...$engagement->summarize($sessions),
                    'subjectLabel' => $subjectType !== null ? $subjects->label($subjectType) : null,
                    'subjectName' => $subjectType !== null ? $subjects->name($subjectType, (int) $this->visitor->subject_id) : null,
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.visitor-detail', $data)
                ->layout($this->layoutName(), ['title' => __('Visiteur').' · '.__('Analytics')]),
        );
    }
}
