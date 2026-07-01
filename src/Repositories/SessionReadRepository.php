<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Models\Session;

final readonly class SessionReadRepository
{
    /**
     * The visitor's currently open session: not ended, and still active within
     * the timeout window. Older idle sessions are treated as finished.
     */
    public function findOpenForVisitor(int $visitorId, CarbonImmutable $activeSince): ?Session
    {
        return Session::query()
            ->where('visitor_id', $visitorId)
            ->whereNull('ended_at')
            ->where('last_activity_at', '>=', $activeSince)
            ->latest('last_activity_at')
            ->first();
    }
}
