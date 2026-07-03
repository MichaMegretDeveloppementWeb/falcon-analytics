<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\IngestionContext;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class SessionWriteRepository
{
    public function start(Visitor $visitor, IngestionContext $context, CarbonImmutable $startedAt): Session
    {
        return Session::create([
            'visitor_id' => $visitor->id,
            'started_at' => $startedAt,
            'last_activity_at' => $startedAt,
            'ip' => $context->ip,
            'country' => $context->country,
            'region' => $context->region,
            'city' => $context->city,
            'latitude' => $context->latitude,
            'longitude' => $context->longitude,
            'device_type' => $context->deviceType,
            'device_brand' => $context->deviceBrand,
            'device_model' => $context->deviceModel,
            'browser' => $context->browser,
            'browser_version' => $context->browserVersion,
            'os' => $context->os,
            'os_version' => $context->osVersion,
            'is_bot' => $context->isBot,
            'referrer' => $context->referrer,
            'source' => $context->source,
            'utm_source' => $context->utmSource,
            'utm_medium' => $context->utmMedium,
            'utm_campaign' => $context->utmCampaign,
            'utm_content' => $context->utmContent,
            'utm_term' => $context->utmTerm,
            'landing_route' => $context->landingRoute,
            'landing_url' => $context->landingUrl,
            'subject_type' => $context->subjectType,
            'subject_id' => $context->subjectId,
            'pageview_count' => 0,
            'event_count' => 0,
        ]);
    }

    /**
     * Atomically increment the counters, remember the last page view URL (so a
     * later reload can be collapsed), and advance the activity timestamp forward
     * only, in a single UPDATE: an out-of-order deferred batch (an older one
     * committing last) must never regress last_activity_at, which the session
     * closure relies on. The CASE keeps last_activity_at when the incoming stamp
     * is older, so the whole write stays on the ingestion hot path as one query.
     */
    public function recordActivity(Session $session, CarbonImmutable $lastActivityAt, int $pageviewDelta, int $eventDelta, ?string $lastPageviewUrl): void
    {
        $stamp = $lastActivityAt->toDateTimeString();

        $session->newQuery()
            ->whereKey($session->getKey())
            ->update([
                'pageview_count' => DB::raw('pageview_count + '.$pageviewDelta),
                'event_count' => DB::raw('event_count + '.$eventDelta),
                'last_pageview_url' => $lastPageviewUrl,
                'last_activity_at' => DB::raw("CASE WHEN last_activity_at < '{$stamp}' THEN '{$stamp}' ELSE last_activity_at END"),
            ]);
    }

    /**
     * Close sessions idle past the timeout by stamping ended_at deterministically
     * at last_activity_at + timeout (never the sweep time), in bounded batches.
     * Returns the number of sessions closed.
     */
    public function closeIdleSessions(CarbonImmutable $idleBefore, int $timeoutMinutes): int
    {
        $closed = 0;

        Session::query()
            ->whereNull('ended_at')
            ->where('last_activity_at', '<', $idleBefore)
            ->select(['id', 'last_activity_at'])
            ->chunkById(500, function (Collection $sessions) use ($timeoutMinutes, &$closed): void {
                foreach ($sessions as $session) {
                    Session::query()
                        ->whereKey($session->id)
                        ->update(['ended_at' => $session->last_activity_at->addMinutes($timeoutMinutes)]);

                    $closed++;
                }
            });

        return $closed;
    }
}
