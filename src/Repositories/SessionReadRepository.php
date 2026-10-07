<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Models\Session;

/** @internal */
final readonly class SessionReadRepository
{
    /**
     * The visitor's currently open session on a given physical browser: not
     * ended, and still active within the timeout window. Older idle sessions
     * are treated as finished. The browser key keeps the sessions of a merged
     * profile (one person, several devices) from blending into each other.
     */
    public function findOpenForVisitor(int $visitorId, CarbonImmutable $activeSince, string $browserKey): ?Session
    {
        return Session::query()
            ->where('visitor_id', $visitorId)
            ->where('browser_key', $browserKey)
            ->whereNull('ended_at')
            ->where('last_activity_at', '>=', $activeSince)
            ->latest('last_activity_at')
            ->first();
    }

    /**
     * The same open session, provided the host's session vouched for its
     * subject since a given moment · what a page's leftover may still join.
     */
    public function findConfirmedOpenForVisitor(int $visitorId, string $browserKey, CarbonImmutable $activeSince, CarbonImmutable $confirmedSince): ?Session
    {
        return Session::query()
            ->where('visitor_id', $visitorId)
            ->where('browser_key', $browserKey)
            ->whereNull('ended_at')
            ->where('last_activity_at', '>=', $activeSince)
            ->where('subject_confirmed_at', '>=', $confirmedSince)
            ->latest('last_activity_at')
            ->first();
    }
}
