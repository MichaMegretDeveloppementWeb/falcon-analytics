<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin;

use Falcon\Analytics\Enums\Authorization\Ability;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Livewire\Admin\Concerns\AsksTheScreenAbility;
use Falcon\Analytics\Livewire\Admin\Concerns\RecoversFromReadFailure;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Services\Dashboard\SessionDetailBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A single session in detail: its main information and the chronological journey
 * (pages visited, clicks nested under their page, time spent). Read-only.
 *
 * @internal
 */
final class SessionDetailPage extends Component
{
    use AsksTheScreenAbility;
    use RecoversFromReadFailure;

    /** What the screen shows of the session's visitor. */
    private const VISITOR = 'visitor:id,uuid,subject_type,subject_id,session_count,first_seen_at,last_seen_at';

    #[Locked]
    public int $sessionId;

    /** The session as this request read it · kept for the request, never between two. */
    private ?Session $read = null;

    public function mount(Session $session): void
    {
        $this->sessionId = $session->id;
        $this->read = $session->load(self::VISITOR);
    }

    public function render(SessionDetailBuilder $details, EventRegistry $eventRegistry): View
    {
        return $this->guardedRender(
            function () use ($details, $eventRegistry): array {
                $session = $this->read ??= Session::query()->with(self::VISITOR)->findOrFail($this->sessionId);

                $conversionNames = [];
                foreach ($eventRegistry->all() as $declared) {
                    if ($declared->isConversion()) {
                        $conversionNames[] = $declared->name;
                    }
                }

                return [
                    'detail' => $details->build($session, $session->events()->orderBy('occurred_at')->orderBy('id')->get(), $conversionNames),
                    'mayOpenVisitors' => Gate::allows(Ability::Visitors),
                ];
            },
            fn (array $data): View => view('analytics::livewire.dashboard.session-detail', $data),
        );
    }

    protected function screenAbility(): Ability
    {
        return Ability::Sessions;
    }
}
