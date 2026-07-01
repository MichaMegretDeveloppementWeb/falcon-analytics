<?php

declare(strict_types=1);

namespace Falcon\Analytics\Actions;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\IncomingBatch;
use Falcon\Analytics\DTOs\IncomingEvent;
use Falcon\Analytics\DTOs\IngestionContext;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\EventWriteRepository;
use Falcon\Analytics\Repositories\SessionReadRepository;
use Falcon\Analytics\Repositories\SessionWriteRepository;
use Falcon\Analytics\Repositories\VisitorWriteRepository;
use Illuminate\Support\Facades\DB;

final readonly class IngestEventsAction
{
    public function __construct(
        private VisitorWriteRepository $visitors,
        private SessionReadRepository $sessionReads,
        private SessionWriteRepository $sessions,
        private EventWriteRepository $events,
    ) {}

    public function execute(IngestionContext $context, IncomingBatch $batch): void
    {
        DB::transaction(function () use ($context, $batch): void {
            $now = CarbonImmutable::now();

            // Trivial lookup by unique key: kept inline in the orchestration.
            $visitor = Visitor::query()->where('uuid', $context->visitorUuid)->first();

            if ($visitor === null) {
                $visitor = $this->visitors->create($context->visitorUuid, $now, $this->subject($context));
            } else {
                $this->visitors->markSeen($visitor, $now, $this->subject($context));
            }

            $timeout = (int) config('analytics.session.timeout_minutes');
            $session = $this->sessionReads->findOpenForVisitor($visitor->id, $now->subMinutes($timeout));

            if ($session === null) {
                $session = $this->sessions->start($visitor, $context, $now);
                $this->visitors->incrementSessionCount($visitor);
            }

            // Heartbeats keep the session alive but are never stored as rows.
            $storable = array_values(array_filter(
                $batch->events,
                fn (IncomingEvent $event): bool => $event->type !== EventType::Heartbeat,
            ));

            $this->events->insertBatch($this->rows($session, $visitor, $context, $storable));

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
     * @return array{type: string, id: int}|null
     */
    private function subject(IngestionContext $context): ?array
    {
        return $context->subjectType !== null && $context->subjectId !== null
            ? ['type' => $context->subjectType, 'id' => $context->subjectId]
            : null;
    }

    /**
     * @param  list<IncomingEvent>  $events
     * @return list<array<string, mixed>>
     */
    private function rows(Session $session, Visitor $visitor, IngestionContext $context, array $events): array
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
            'props' => $event->props !== null ? json_encode($event->props) : null,
            'value' => $event->value,
            'subject_type' => $context->subjectType,
            'subject_id' => $context->subjectId,
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
