<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin;

use Falcon\Analytics\Actions\ForgetVisitorAction;
use Falcon\Analytics\Enums\Authorization\Ability;
use Falcon\Analytics\Livewire\Admin\Concerns\AsksTheScreenAbility;
use Falcon\Analytics\Livewire\Admin\Concerns\RecoversFromReadFailure;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\Dashboard\VisitorProfileReadRepository;
use Falcon\Analytics\Services\Dashboard\VisitorDetailBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * A single visitor in detail: their identity, headline figures and the list of
 * their sessions (each linking to the session detail). Read-only apart from the
 * GDPR erasure action.
 *
 * @internal
 */
final class VisitorDetailPage extends Component
{
    use AsksTheScreenAbility;
    use RecoversFromReadFailure;
    use WithPagination;

    private const PER_PAGE = 20;

    #[Locked]
    public int $visitorId;

    /** The visitor as this request read it · kept for the request, never between two. */
    private ?Visitor $read = null;

    public function mount(Visitor $visitor): void
    {
        $this->visitorId = $visitor->id;
        $this->read = $visitor;

        // A folded profile holds no data of its own: a link to it lands on the canonical profile.
        if ($visitor->merged_into_id !== null) {
            $this->redirect(route('analytics.admin.visitors.show', $visitor->merged_into_id));
        }
    }

    /**
     * Erase this visitor's data (GDPR right to erasure) and return to the list.
     * Logged on the analytics channel for an audit trail; a failure degrades to
     * an inline error rather than a raw 500.
     */
    public function forget(ForgetVisitorAction $action): void
    {
        $this->authorize(Ability::VisitorsDelete, $this->visitor());

        try {
            $action->execute($this->visitor());
        } catch (\Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Analytics visitor erasure failed.', [
                'visitor_id' => $this->visitorId,
                'exception' => $e,
            ]);

            $this->addError('visitor-erasure-failed', __('La suppression a échoué. Réessayez dans un instant.'));

            return;
        }

        Log::channel(config('analytics.log_channel'))->notice('Visitor.erased', [
            'visitor_id' => $this->visitorId,
            'actor_user_id' => auth()->id(),
        ]);

        $this->redirect(route('analytics.admin.visitors'));
    }

    public function render(VisitorDetailBuilder $details, VisitorProfileReadRepository $repository): View
    {
        return $this->guardedRender(
            fn (): array => [
                'detail' => $details->build($this->visitor(), $repository->engagement($this->visitorId)),
                'sessions' => $details->sessions($repository->paginateSessions($this->visitorId, self::PER_PAGE)),
                'mayDelete' => Gate::allows(Ability::VisitorsDelete, $this->visitor()),
                'mayOpenSessions' => Gate::allows(Ability::Sessions),
            ],
            fn (array $data): View => view('analytics::livewire.dashboard.visitor-detail', $data),
        );
    }

    protected function screenAbility(): Ability
    {
        return Ability::Visitors;
    }

    private function visitor(): Visitor
    {
        return $this->read ??= Visitor::query()->findOrFail($this->visitorId);
    }
}
