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
    public function start(Visitor $visitor, IngestionContext $context, CarbonImmutable $startedAt, string $browserKey): Session
    {
        return Session::create([
            'visitor_id' => $visitor->id,
            'browser_key' => $browserKey,
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
            'mkt_params' => $context->mktParams !== [] ? $context->mktParams : null,
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
     *
     * **Les valeurs sont liees, elles ne sont plus interpolees.** Les deux
     * deltas et l'horodatage entraient dans le SQL par concatenation ; ils
     * passent desormais en parametres, et la requete est litterale de bout en
     * bout. Le nom de la table vient de `Session::TABLE`, donc il n'est toujours
     * ecrit qu'a un seul endroit. Une seule requete, comme avant.
     */
    public function recordActivity(Session $session, CarbonImmutable $lastActivityAt, int $pageviewDelta, int $eventDelta, ?string $lastPageviewUrl): void
    {
        $stamp = $lastActivityAt->toDateTimeString();

        DB::update(
            'UPDATE '.Session::TABLE.' SET '
            .'pageview_count = pageview_count + ?, '
            .'event_count = event_count + ?, '
            .'last_pageview_url = ?, '
            .'last_activity_at = CASE WHEN last_activity_at < ? THEN ? ELSE last_activity_at END '
            .'WHERE id = ?',
            [$pageviewDelta, $eventDelta, $lastPageviewUrl, $stamp, $stamp, $session->getKey()],
        );
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
