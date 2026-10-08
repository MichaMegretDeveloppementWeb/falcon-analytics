<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\VisitorWriteRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Identity merging: one known person = one visitor profile. When a subject turns
 * out to own several visitor rows (several browsers or devices), the newer rows
 * fold into the oldest one: their sessions and events move over, and the folded
 * row becomes an alias whose uuid keeps routing future beacons from that browser
 * to the canonical profile. Sessions keep their `browser_key`, so the physical
 * device separation survives the merge.
 *
 * Every write here stands or falls with the others, so it runs inside the
 * caller's transaction and refuses to run without one.
 *
 * @internal
 */
final readonly class VisitorMerger
{
    public function __construct(private VisitorWriteRepository $visitors) {}

    /**
     * Fold the alias visitor into the canonical one: move its sessions and
     * events, tombstone it (merged_into_id) and widen the canonical's seen
     * window and session count. Returns the canonical, refreshed.
     */
    public function execute(Visitor $alias, Visitor $canonical): Visitor
    {
        $this->assertInsideATransaction(__FUNCTION__);

        Event::query()->where('visitor_id', $alias->id)->update(['visitor_id' => $canonical->id]);
        Session::query()->where('visitor_id', $alias->id)->update(['visitor_id' => $canonical->id]);

        $alias->update([
            'merged_into_id' => $canonical->id,
            'session_count' => 0,
        ]);

        $canonical->update([
            'first_seen_at' => $canonical->first_seen_at->min($alias->first_seen_at),
            'last_seen_at' => $canonical->last_seen_at->max($alias->last_seen_at),
            'session_count' => Session::query()->where('visitor_id', $canonical->id)->count(),
        ]);

        return $canonical->refresh();
    }

    /**
     * Invariant: an identified session always lives on its subject's canonical
     * profile. Pull the subject's identified sessions (and their events) off any
     * other visitor, typically after a login on a shared browser that predates
     * the subject's own profile.
     */
    public function relocateForeignSessions(string $subjectType, int $subjectId, Visitor $canonical): void
    {
        $this->assertInsideATransaction(__FUNCTION__);

        $sessionIds = Session::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('visitor_id', '!=', $canonical->id)
            ->pluck('id');

        if ($sessionIds->isEmpty()) {
            return;
        }

        $previousVisitorIds = Session::query()
            ->whereIn('id', $sessionIds)
            ->distinct()
            ->pluck('visitor_id');

        Event::query()->whereIn('session_id', $sessionIds)->update(['visitor_id' => $canonical->id]);
        Session::query()->whereIn('id', $sessionIds)->update(['visitor_id' => $canonical->id]);

        foreach ([$canonical->id, ...$previousVisitorIds] as $visitorId) {
            $this->recountSessions((int) $visitorId);
        }
    }

    /**
     * Before profiles are erased · the sessions other subjects left on them, a
     * shared browser, move to those subjects' own profiles, made for them when
     * they have none, so erasing one person never erases another.
     *
     * @param  list<int>  $profileIds
     * @param  array{type: string, id: int}|null  $owner  whose sessions stay, to go with the profiles
     */
    public function rehomeOtherSubjects(array $profileIds, ?array $owner): void
    {
        $this->assertInsideATransaction(__FUNCTION__);

        $others = Session::query()
            ->whereIn('visitor_id', $profileIds)
            ->whereNotNull('subject_type')
            ->when($owner !== null, fn (Builder $query) => $query->whereNot(
                fn (Builder $theirs) => $theirs->where('subject_type', $owner['type'] ?? null)->where('subject_id', $owner['id'] ?? null),
            ))
            ->groupBy('subject_type', 'subject_id')
            ->selectRaw('subject_type, subject_id, min(started_at) as first_seen_at, max(last_activity_at) as last_seen_at')
            ->toBase()
            ->get();

        // One pass per other subject who signed in on these browsers · a handful at most.
        foreach ($others as $other) {
            $subject = ['type' => (string) $other->subject_type, 'id' => (int) $other->subject_id];

            $home = $this->visitors->canonicalFor($subject['type'], $subject['id'])
                ?? $this->visitors->createFor(
                    $subject,
                    CarbonImmutable::parse((string) $other->first_seen_at),
                    CarbonImmutable::parse((string) $other->last_seen_at),
                );

            $this->relocateForeignSessions($subject['type'], $subject['id'], $home);
        }
    }

    private function assertInsideATransaction(string $method): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException(self::class."::{$method}() writes rows that stand or fall together: call it inside the caller's transaction.");
        }
    }

    private function recountSessions(int $visitorId): void
    {
        Visitor::query()->whereKey($visitorId)->update([
            'session_count' => Session::query()->where('visitor_id', $visitorId)->count(),
        ]);
    }
}
