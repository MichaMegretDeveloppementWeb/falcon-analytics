<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\SessionSubjectAttribution;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;

/**
 * Resolves the subject each session is displayed under. A session identified
 * during its lifetime keeps its own subject; an anonymous session falls back to
 * the subject stitched on its visitor, so every session of a known person is
 * named even when they never logged in during it (the display then flags the
 * session as "not connected").
 *
 * The fallback is withheld for a visitor whose identified sessions point to
 * more than one distinct subject (shared browser, client and lessor accounts
 * side by side): naming its anonymous sessions would be guesswork.
 */
final class SessionSubjectAttributor
{
    /**
     * Attributions keyed by session id. Sessions must have their `visitor`
     * relation loaded; anonymous sessions of an anonymous (or ambiguous)
     * visitor produce no entry.
     *
     * @param  iterable<Session>  $sessions
     * @return array<int, SessionSubjectAttribution>
     */
    public function attribute(iterable $sessions): array
    {
        $attributions = [];
        $candidates = [];

        foreach ($sessions as $session) {
            if ($session->subject_type !== null && $session->subject_id !== null) {
                $attributions[(int) $session->id] = new SessionSubjectAttribution(
                    guard: (string) $session->subject_type,
                    id: (int) $session->subject_id,
                    viaVisitor: false,
                );

                continue;
            }

            $visitor = $session->visitor;

            if ($visitor->subject_type !== null && $visitor->subject_id !== null) {
                $candidates[(int) $session->id] = $visitor;
            }
        }

        if ($candidates === []) {
            return $attributions;
        }

        $ambiguous = $this->ambiguousVisitorIds($candidates);

        foreach ($candidates as $sessionId => $visitor) {
            if (in_array((int) $visitor->id, $ambiguous, true)) {
                continue;
            }

            $attributions[$sessionId] = new SessionSubjectAttribution(
                guard: (string) $visitor->subject_type,
                id: (int) $visitor->subject_id,
                viaVisitor: true,
            );
        }

        return $attributions;
    }

    /**
     * Ids of the candidate visitors whose identified sessions carry a subject
     * different from the one stitched on the visitor, in a single batched query.
     *
     * @param  array<int, Visitor>  $candidates
     * @return list<int>
     */
    private function ambiguousVisitorIds(array $candidates): array
    {
        $visitors = [];
        foreach ($candidates as $visitor) {
            $visitors[(int) $visitor->id] = $visitor;
        }

        $pairs = Session::query()
            ->whereIn('visitor_id', array_keys($visitors))
            ->whereNotNull('subject_id')
            ->distinct()
            ->get(['visitor_id', 'subject_type', 'subject_id']);

        $ambiguous = [];
        foreach ($pairs as $pair) {
            $visitor = $visitors[(int) $pair->visitor_id] ?? null;

            if ($visitor === null) {
                continue;
            }

            if ((string) $pair->subject_type !== (string) $visitor->subject_type
                || (int) $pair->subject_id !== (int) $visitor->subject_id) {
                $ambiguous[] = (int) $visitor->id;
            }
        }

        return array_values(array_unique($ambiguous));
    }
}
