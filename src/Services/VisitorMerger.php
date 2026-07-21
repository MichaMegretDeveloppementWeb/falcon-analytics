<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services;

use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Illuminate\Support\Facades\DB;

/**
 * Identity merging: one known person = one visitor profile. When a subject turns
 * out to own several visitor rows (several browsers or devices), the newer rows
 * fold into the oldest one: their sessions and events move over, and the folded
 * row becomes an alias whose uuid keeps routing future beacons from that browser
 * to the canonical profile. Sessions keep their `browser_key`, so the physical
 * device separation survives the merge.
 */
final readonly class VisitorMerger
{
    /**
     * Fold the alias visitor into the canonical one: move its sessions and
     * events, tombstone it (merged_into_id) and widen the canonical's seen
     * window and session count. Returns the canonical, refreshed.
     */
    public function execute(Visitor $alias, Visitor $canonical): Visitor
    {
        DB::transaction(function () use ($alias, $canonical): void {
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
        });

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
        $sessionIds = Session::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('visitor_id', '!=', $canonical->id)
            ->pluck('id');

        if ($sessionIds->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($sessionIds, $canonical): void {
            $previousVisitorIds = Session::query()
                ->whereIn('id', $sessionIds)
                ->distinct()
                ->pluck('visitor_id');

            Event::query()->whereIn('session_id', $sessionIds)->update(['visitor_id' => $canonical->id]);
            Session::query()->whereIn('id', $sessionIds)->update(['visitor_id' => $canonical->id]);

            foreach ([$canonical->id, ...$previousVisitorIds] as $visitorId) {
                $this->recountSessions((int) $visitorId);
            }
        });
    }

    /**
     * One-shot consolidation of pre-merge data, run by the migration that
     * introduces identity merging: fold the duplicate profiles of every subject
     * into their oldest one, then apply the identified-session invariant.
     */
    public function consolidateExisting(): void
    {
        $groups = Visitor::query()
            ->whereNotNull('subject_id')
            ->whereNull('merged_into_id')
            ->orderBy('first_seen_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Visitor $visitor): string => $visitor->subject_type.':'.$visitor->subject_id);

        foreach ($groups as $visitors) {
            /** @var Visitor $canonical */
            $canonical = $visitors->shift();

            foreach ($visitors as $alias) {
                $canonical = $this->execute($alias, $canonical);
            }

            $this->relocateForeignSessions((string) $canonical->subject_type, (int) $canonical->subject_id, $canonical);
        }
    }

    private function recountSessions(int $visitorId): void
    {
        Visitor::query()->whereKey($visitorId)->update([
            'session_count' => Session::query()->where('visitor_id', $visitorId)->count(),
        ]);
    }
}
