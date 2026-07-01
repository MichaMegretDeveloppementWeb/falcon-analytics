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
use Illuminate\Support\Facades\DB;

final readonly class IngestEventsAction
{
    private const MAX_PROPS = 30;

    public function __construct(
        private VisitorWriteRepository $visitors,
        private SessionReadRepository $sessionReads,
        private SessionWriteRepository $sessions,
        private EventWriteRepository $events,
        private SessionContextEnricher $enricher,
    ) {}

    /**
     * @param  array{type: string, id: int}|null  $subject
     */
    public function execute(string $visitorUuid, ?array $subject, RequestSnapshot $snapshot, IncomingBatch $batch): void
    {
        $now = CarbonImmutable::now();

        // Resolved outside the transaction: firstOrCreate is race-safe, and a
        // conflicting insert must not poison the transaction below.
        $visitor = $this->visitors->resolve($visitorUuid, $now, $subject);

        DB::transaction(function () use ($subject, $snapshot, $batch, $visitor, $now): void {
            // Serialize concurrent beacons for this visitor so two tabs cannot each
            // start a session (which would duplicate sessions and inflate counts).
            $locked = $visitor->newQuery()->whereKey($visitor->getKey())->lockForUpdate()->firstOrFail();

            $timeout = (int) config('analytics.session.timeout_minutes');
            $session = $this->sessionReads->findOpenForVisitor($locked->id, $now->subMinutes($timeout));

            if ($session === null) {
                // Heavy UA/geo/source enrichment runs only when a session starts.
                $context = $this->enricher->enrich($snapshot, $batch, $subject);
                $session = $this->sessions->start($locked, $context, $now);
                $this->visitors->incrementSessionCount($locked);
            }

            // Heartbeats keep the session alive but are never stored as rows.
            $storable = array_values(array_filter(
                $batch->events,
                fn (IncomingEvent $event): bool => $event->type !== EventType::Heartbeat,
            ));

            $this->events->insertBatch($this->rows($session, $locked, $subject, $storable));

            $pageviews = count(array_filter(
                $storable,
                fn (IncomingEvent $event): bool => $event->type === EventType::Pageview,
            ));

            $this->sessions->recordActivity(
                $session,
                $this->lastActivity($batch->events),
                $pageviews,
                count($storable),
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
            'props' => $this->encodeProps($event->props),
            'value' => $event->value,
            'subject_type' => $subject['type'] ?? null,
            'subject_id' => $subject['id'] ?? null,
        ], $events);
    }

    /**
     * Keep only scalar values and cap the key count so a hostile client cannot
     * amplify storage; JSON_INVALID_UTF8_SUBSTITUTE keeps bad bytes from failing
     * the encode (which would otherwise write a literal false into the column).
     *
     * @param  array<string, mixed>|null  $props
     */
    private function encodeProps(?array $props): ?string
    {
        if ($props === null) {
            return null;
        }

        $clean = [];
        foreach ($props as $key => $value) {
            if (count($clean) >= self::MAX_PROPS) {
                break;
            }
            if ($value === null || is_scalar($value)) {
                $clean[(string) $key] = $value;
            }
        }

        return $clean === [] ? null : json_encode($clean, JSON_INVALID_UTF8_SUBSTITUTE);
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
