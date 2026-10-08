<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Models\Session;
use Illuminate\Database\Eloquent\Builder;

/** @internal */
final readonly class SessionReadRepository
{
    /**
     * The visitor's currently open session on a given physical browser: not
     * ended, and still active within the timeout window. Older idle sessions
     * are treated as finished. The browser key keeps the sessions of a merged
     * profile (one person, several devices) from blending into each other.
     *
     * An identified send never joins the session of another subject: one
     * session, one person, whoever signs in on the browser meanwhile.
     *
     * @param  array{type: string, id: int}|null  $subject
     */
    public function findOpenForVisitor(int $visitorId, CarbonImmutable $activeSince, string $browserKey, ?array $subject = null): ?Session
    {
        return Session::query()
            ->where('visitor_id', $visitorId)
            ->where('browser_key', $browserKey)
            ->whereNull('ended_at')
            ->where('last_activity_at', '>=', $activeSince)
            ->when($subject !== null, fn (Builder $query): Builder => $query->where(function (Builder $own) use ($subject): void {
                $own->whereNull('subject_type')->orWhere(function (Builder $same) use ($subject): void {
                    $same->where('subject_type', $subject['type'] ?? null)->where('subject_id', $subject['id'] ?? null);
                });
            }))
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
