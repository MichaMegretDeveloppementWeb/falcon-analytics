<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\VisitorWriteRepository;

/**
 * Converges a browser's profile on the person a batch is identified as,
 * enforcing "one known person = one profile":
 *
 * - the browser's profile is unowned and the person already has a profile
 *   elsewhere -> the two fold together (oldest survives);
 * - the browser belongs to someone else (shared device) -> the batch is routed
 *   to the person's own profile when there is one, the browser keeps its owner;
 * - first identification ever -> the browser's profile simply becomes theirs.
 *
 * A fold and the sessions it pulls in stand or fall together, so this runs
 * inside the caller's transaction.
 *
 * @internal
 */
final readonly class VisitorProfileResolver
{
    public function __construct(
        private VisitorWriteRepository $visitors,
        private VisitorMerger $merges,
    ) {}

    /**
     * The canonical profile the batch lands on.
     *
     * @param  array{type: string, id: int}  $subject
     */
    public function converge(Visitor $browser, CarbonImmutable $seenAt, array $subject): Visitor
    {
        if ($browser->subject_type === $subject['type'] && $browser->subject_id === $subject['id']) {
            $other = $this->visitors->canonicalFor($subject['type'], $subject['id'], excludeId: $browser->id);

            if ($other === null) {
                $this->merges->relocateForeignSessions($subject['type'], $subject['id'], $browser);

                return $browser;
            }

            $canonical = $this->fold($browser, $other);
            $this->merges->relocateForeignSessions($subject['type'], $subject['id'], $canonical);

            return $canonical;
        }

        $own = $this->visitors->canonicalFor($subject['type'], $subject['id']);

        if ($own !== null) {
            $this->visitors->markSeen($own, $seenAt, $subject);

            return $own;
        }

        return $browser;
    }

    /**
     * Merge two profiles of the same person, keeping the oldest as canonical
     * (first seen, then id — the same order canonicalFor() resolves with).
     */
    private function fold(Visitor $a, Visitor $b): Visitor
    {
        $aIsOlder = $a->first_seen_at->lessThan($b->first_seen_at)
            || ($a->first_seen_at->equalTo($b->first_seen_at) && $a->id < $b->id);

        [$canonical, $alias] = $aIsOlder ? [$a, $b] : [$b, $a];

        return $this->merges->execute($alias, $canonical);
    }
}
