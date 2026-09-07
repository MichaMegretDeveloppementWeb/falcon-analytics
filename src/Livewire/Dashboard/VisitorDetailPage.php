<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Actions\ForgetVisitorAction;
use Falcon\Analytics\Livewire\Dashboard\Concerns\RecoversFromReadFailure;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\Dashboard\VisitorProfileReadRepository;
use Falcon\Analytics\Services\Dashboard\VisitorEngagementCalculator;
use Falcon\Analytics\Services\SubjectResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * A single visitor in detail: their identity, headline figures and the list of
 * their sessions (each linking to the session detail). Read-only apart from the
 * GDPR erasure action.
 */
final class VisitorDetailPage extends Component
{
    use RecoversFromReadFailure;
    use WithPagination;

    private const PER_PAGE = 20;

    public Visitor $visitor;

    public function mount(Visitor $visitor): void
    {
        $this->visitor = $visitor;

        // A folded profile has no data of its own anymore: an old link or
        // bookmark lands on the canonical profile instead.
        if ($visitor->merged_into_id !== null) {
            $this->redirect(route(
                config('analytics.dashboard.route_name', 'analytics').'.visitors.show',
                $visitor->merged_into_id,
            ));
        }
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

        Log::channel(config('analytics.log_channel'))->notice('Visitor.erased', [
            'visitor_id' => $this->visitor->id,
            'actor_user_id' => auth()->id(),
        ]);

        $this->redirect(route(config('analytics.dashboard.route_name', 'analytics').'.visitors'));
    }

    public function render(SubjectResolver $subjects, VisitorProfileReadRepository $repository, VisitorEngagementCalculator $engagement): View
    {
        return $this->guardedRender(
            function () use ($subjects, $repository, $engagement): array {
                $subjectType = $this->visitor->subject_type;

                return [
                    'visitor' => $this->visitor,
                    'sessions' => $repository->paginateSessions($this->visitor->id, self::PER_PAGE),
                    ...$engagement->summarize($repository->engagement($this->visitor->id)),
                    'subjectLabel' => $subjectType !== null ? $subjects->label($subjectType) : null,
                    'subjectName' => $subjectType !== null ? $subjects->name($subjectType, (int) $this->visitor->subject_id) : null,
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.visitor-detail', $data),
        );
    }
}
