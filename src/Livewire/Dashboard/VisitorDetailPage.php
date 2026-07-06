<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Actions\ForgetVisitorAction;
use Falcon\Analytics\Livewire\Dashboard\Concerns\RecoversFromReadFailure;
use Falcon\Analytics\Livewire\Dashboard\Concerns\ResolvesDashboardLayout;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Services\SubjectResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
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

    public string $deleteError = '';

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

            $this->deleteError = __('La suppression a échoué. Réessayez dans un instant.');

            return;
        }

        Log::channel(config('analytics.log_channel'))->info('Analytics visitor erased.', ['visitor_id' => $this->visitor->id]);

        $this->redirect(route(config('analytics.dashboard.route_name', 'analytics').'.visitors'));
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
                $count = $sessions->count();
                $totalPageviews = (int) $sessions->sum('pageview_count');
                $totalSeconds = (int) $sessions->sum(fn (Session $s): int => (int) $s->started_at->diffInSeconds($s->last_activity_at));

                return [
                    'visitor' => $this->visitor,
                    'sessions' => $sessions,
                    'totalPageviews' => $totalPageviews,
                    'avgSeconds' => $count > 0 ? (int) round($totalSeconds / $count) : 0,
                    'pagesPerSession' => $count > 0 ? round($totalPageviews / $count, 1) : 0.0,
                    'devices' => $sessions->groupBy(fn (Session $s): string => (string) $s->device_type)->map->count()->sortDesc()->all(),
                    'sources' => $sessions->groupBy(fn (Session $s): string => $s->source ?: 'direct')->map->count()->sortDesc()->all(),
                    'subjectLabel' => $subjectType !== null ? $subjects->label($subjectType) : null,
                    'subjectName' => $subjectType !== null ? $subjects->name($subjectType, (int) $this->visitor->subject_id) : null,
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.visitor-detail', $data)
                ->layout($this->layoutName(), ['title' => __('Visiteur').' · '.__('Analytics')]),
        );
    }
}
