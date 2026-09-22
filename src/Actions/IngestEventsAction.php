<?php

declare(strict_types=1);

namespace Falcon\Analytics\Actions;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\IncomingBatch;
use Falcon\Analytics\DTOs\IncomingEvent;
use Falcon\Analytics\DTOs\RequestSnapshot;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\EventWriteRepository;
use Falcon\Analytics\Repositories\SessionReadRepository;
use Falcon\Analytics\Repositories\SessionWriteRepository;
use Falcon\Analytics\Repositories\VisitorWriteRepository;
use Falcon\Analytics\Services\SessionContextEnricher;
use Falcon\Analytics\Services\VisitorProfileResolver;
use Falcon\Analytics\Support\PropsEncoder;
use Falcon\Analytics\Support\StoredUrl;
use Illuminate\Support\Facades\DB;

/** @internal */
final readonly class IngestEventsAction
{
    public function __construct(
        private VisitorProfileResolver $profiles,
        private VisitorWriteRepository $visitors,
        private SessionReadRepository $sessionReads,
        private SessionWriteRepository $sessions,
        private EventWriteRepository $events,
        private SessionContextEnricher $enricher,
        private PropsEncoder $propsEncoder,
    ) {}

    /**
     * @param  array{type: string, id: int}|null  $subject
     */
    public function execute(string $visitorUuid, ?array $subject, RequestSnapshot $snapshot, IncomingBatch $batch): void
    {
        $now = CarbonImmutable::now();
        $visitor = $this->profileFor($visitorUuid, $now, $subject);

        DB::transaction(function () use ($visitorUuid, $subject, $snapshot, $batch, $visitor, $now): void {
            // Serialize concurrent beacons for this visitor so two tabs cannot each
            // start a session (which would duplicate sessions and inflate counts).
            $locked = $visitor->newQuery()->whereKey($visitor->getKey())->lockForUpdate()->firstOrFail();

            $session = $this->openSession($locked, $visitorUuid, $subject, $snapshot, $batch, $now);

            $this->record($session, $locked, $subject, $batch);
        });
    }

    /**
     * The canonical profile the batch lands on.
     *
     * The browser is found outside any transaction, where firstOrCreate can
     * recover a concurrent insert. Its convergence runs in a transaction of its
     * own: inside the ingestion's, its unlocked reads would fix the snapshot
     * before the visitor lock, and a session a second tab has just opened would
     * go unseen.
     *
     * @param  array{type: string, id: int}|null  $subject
     */
    private function profileFor(string $uuid, CarbonImmutable $now, ?array $subject): Visitor
    {
        $browser = $this->visitors->resolve($uuid, $now, $subject);

        if ($subject === null) {
            return $browser;
        }

        return DB::transaction(fn (): Visitor => $this->profiles->converge($browser, $now, $subject));
    }

    /**
     * The session this browser has open, or a new one, enriched as it starts.
     *
     * Scoped to the physical browser (browser key): two devices of the same
     * person browsing at once never blend into one session, even though they
     * share the canonical profile.
     *
     * @param  array{type: string, id: int}|null  $subject
     */
    private function openSession(Visitor $visitor, string $browserKey, ?array $subject, RequestSnapshot $snapshot, IncomingBatch $batch, CarbonImmutable $now): Session
    {
        $timeout = (int) config('analytics.session.timeout_minutes');
        $session = $this->sessionReads->findOpenForVisitor($visitor->id, $now->subMinutes($timeout), $browserKey);

        if ($session !== null) {
            return $session;
        }

        $session = $this->sessions->start($visitor, $this->enricher->enrich($snapshot, $batch, $subject), $now, $browserKey);
        $this->visitors->incrementSessionCount($visitor);

        return $session;
    }

    /**
     * Write the batch's stored events and the activity they add to the session.
     *
     * @param  array{type: string, id: int}|null  $subject
     */
    private function record(Session $session, Visitor $visitor, ?array $subject, IncomingBatch $batch): void
    {
        $stored = array_values(array_filter(
            $batch->events,
            fn (IncomingEvent $event): bool => $event->type->isStored(),
        ));

        $kept = $this->withoutReloads($stored, $session->last_pageview_url);

        $this->events->insertBatch($this->rows($session, $visitor, $subject, $kept['events']));

        $this->sessions->recordActivity(
            $session,
            $this->lastActivity($batch->events),
            $kept['pageviews'],
            $kept['clicks'],
            count($kept['events']),
            $kept['lastPageviewUrl'],
        );
    }

    /**
     * The events worth keeping, and what they add up to.
     *
     * Reloading the same URL is not a new view, so consecutive duplicate page
     * views collapse. A fresh session starts from a null last URL: the first
     * view after a timeout always counts, even if the URL is unchanged.
     *
     * @param  list<IncomingEvent>  $events
     * @return array{events: list<IncomingEvent>, pageviews: int, clicks: int, lastPageviewUrl: ?string}
     */
    private function withoutReloads(array $events, ?string $lastPageviewUrl): array
    {
        $kept = [];
        $pageviews = 0;
        $clicks = 0;

        foreach ($events as $event) {
            if ($event->type === EventType::Pageview) {
                if ($event->url !== null && $event->url === $lastPageviewUrl) {
                    continue;
                }
                $lastPageviewUrl = $event->url;
                $pageviews++;
            }

            if ($event->type === EventType::Click) {
                $clicks++;
            }

            $kept[] = $event;
        }

        return ['events' => $kept, 'pageviews' => $pageviews, 'clicks' => $clicks, 'lastPageviewUrl' => $lastPageviewUrl];
    }

    /**
     * @param  array{type: string, id: int}|null  $subject
     * @param  list<IncomingEvent>  $events
     * @return list<array<string, mixed>>
     */
    private function rows(Session $session, Visitor $visitor, ?array $subject, array $events): array
    {
        return array_map(fn (IncomingEvent $event): array => [
            'session_id' => $session->id,
            'visitor_id' => $visitor->id,
            'occurred_at' => $event->occurredAt->toDateTimeString(),
            'type' => $event->type->value,
            'name' => $event->name,
            'route' => $event->route,
            'url' => $event->url,

            // The page, written once here · the path of the route, without
            // host, query string or fragment. Every count of « pages » groups
            // on this column, and the screen displays exactly this.
            'page' => StoredUrl::page($event->url),

            'target_selector' => $event->targetSelector,
            'target_text' => $event->targetText,
            'props' => $this->propsEncoder->encode($event->props),
            'value' => $event->value,
            'subject_type' => $subject['type'] ?? null,
            'subject_id' => $subject['id'] ?? null,
        ], $events);
    }

    /**
     * @param  list<IncomingEvent>  $events
     */
    private function lastActivity(array $events): CarbonImmutable
    {
        if ($events === []) {
            return CarbonImmutable::now();
        }

        $latest = $events[0]->occurredAt;

        foreach ($events as $event) {
            if ($event->occurredAt->greaterThan($latest)) {
                $latest = $event->occurredAt;
            }
        }

        return $latest;
    }
}
