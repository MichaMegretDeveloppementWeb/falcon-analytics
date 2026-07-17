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
use Illuminate\Support\Facades\DB;

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

        // Resolved outside the transaction: firstOrCreate is race-safe, and a
        // conflicting insert must not poison the transaction below. The resolver
        // returns the canonical profile (identity merging), while the raw uuid
        // stays the session's browser key below.
        $visitor = $this->profiles->resolve($visitorUuid, $now, $subject);

        DB::transaction(function () use ($visitorUuid, $subject, $snapshot, $batch, $visitor, $now): void {
            // Serialize concurrent beacons for this visitor so two tabs cannot each
            // start a session (which would duplicate sessions and inflate counts).
            $locked = $visitor->newQuery()->whereKey($visitor->getKey())->lockForUpdate()->firstOrFail();

            // The open session is scoped to the physical browser (browser key):
            // two devices of the same person browsing at once must never blend
            // into one session, even though they share the canonical profile.
            $timeout = (int) config('analytics.session.timeout_minutes');
            $session = $this->sessionReads->findOpenForVisitor($locked->id, $now->subMinutes($timeout), $visitorUuid);

            if ($session === null) {
                // Heavy UA/geo/source enrichment runs only when a session starts.
                $context = $this->enricher->enrich($snapshot, $batch, $subject);
                $session = $this->sessions->start($locked, $context, $now, $visitorUuid);
                $this->visitors->incrementSessionCount($locked);
            }

            // Heartbeats keep the session alive but are never stored as rows.
            $storable = array_values(array_filter(
                $batch->events,
                fn (IncomingEvent $event): bool => $event->type !== EventType::Heartbeat,
            ));

            // Collapse consecutive duplicate page views: reloading the same URL is
            // not a new view. A fresh session starts from a null last URL, so the
            // first view after a timeout always counts even if the URL is unchanged.
            $lastPageviewUrl = $session->last_pageview_url;
            $kept = [];
            $pageviews = 0;

            foreach ($storable as $event) {
                if ($event->type === EventType::Pageview) {
                    if ($event->url !== null && $event->url === $lastPageviewUrl) {
                        continue;
                    }
                    $lastPageviewUrl = $event->url;
                    $pageviews++;
                }

                $kept[] = $event;
            }

            $this->events->insertBatch($this->rows($session, $locked, $subject, $kept));

            $this->sessions->recordActivity(
                $session,
                $this->lastActivity($batch->events),
                $pageviews,
                count($kept),
                $lastPageviewUrl,
            );
        });
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
