<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\VisitorWriteRepository;

/**
 * Resolves the visitor profile a batch belongs to, enforcing "one known person =
 * one profile". The browser's uuid finds (or creates) its profile; when the
 * request carries an authenticated subject, the profile converges on the
 * person's canonical one:
 *
 * - the browser's profile is unowned and the person already has a profile
 *   elsewhere -> the two fold together (oldest survives);
 * - the browser belongs to someone else (shared device) -> the batch is routed
 *   to the person's own profile, the browser keeps its owner;
 * - first identification ever -> the browser's profile simply becomes theirs.
 */
final readonly class VisitorProfileResolver
{
    public function __construct(
        private VisitorWriteRepository $visitors,
        private VisitorMerger $merges,
    ) {}

    /**
     * @param  array{type: string, id: int}|null  $subject
     */
    public function resolve(string $uuid, CarbonImmutable $seenAt, ?array $subject): Visitor
    {
        $browser = $this->visitors->resolve($uuid, $seenAt, $subject);

        if ($subject === null) {
            return $browser;
        }

        if ($browser->subject_type === $subject['type'] && $browser->subject_id === $subject['id']) {
            $other = $this->visitors->canonicalFor($subject['type'], $subject['id'], excludeId: $browser->id);

            if ($other === null) {
                // First identification ever: the browser's profile becomes the
                // person's. Pull in any identified stray sessions (logins on
                // browsers owned by someone else, before this profile existed).
                $this->merges->relocateForeignSessions($subject['type'], $subject['id'], $browser);

                return $browser;
            }

            $canonical = $this->fold($browser, $other);
            $this->merges->relocateForeignSessions($subject['type'], $subject['id'], $canonical);

            return $canonical;
        }

        // Shared browser: it belongs to someone else. The batch is the logged-in
        // person's activity, so it lands on their own profile when they have
        // one; otherwise it stays on the browser's profile, and the sessions'
        // own subject keeps the display truthful until a profile exists.
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
