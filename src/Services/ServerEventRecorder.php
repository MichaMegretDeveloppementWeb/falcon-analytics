<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Actions\IngestEventsAction;
use Falcon\Analytics\Analytics;
use Falcon\Analytics\DTOs\IncomingBatch;
use Falcon\Analytics\DTOs\IncomingEvent;
use Falcon\Analytics\DTOs\RequestSnapshot;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Support\UrlRedactor;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Records events emitted by application code (server-side), as opposed to those
 * captured in the browser by the collector. Same visitor/session, same storage,
 * same funnels: a code-sent event is just an event whose source is the server.
 */
final readonly class ServerEventRecorder
{
    public function __construct(
        private Analytics $analytics,
        private VisitorIdentity $identity,
        private IngestEventsAction $action,
        private UrlRedactor $redactor,
    ) {}

    /**
     * Record a custom event for the current visitor. A no-op when tracking is
     * off or the context is excluded (e.g. an admin); deferred so it never blocks
     * the response. Nothing here ever throws to the caller: analytics must never
     * break the code that emits an event, whatever the context (HTTP, job, CLI).
     *
     * @param  array<string, scalar|null>  $props
     */
    public function record(string $name, ?float $value = null, array $props = []): void
    {
        if (! config('analytics.enabled')) {
            return;
        }

        try {
            if ($this->analytics->isExcluded()) {
                return;
            }

            $request = request();
            $uuid = $this->identity->resolve($request, $this->analytics->hasConsent());
            $subject = $this->analytics->subject();
            $snapshot = new RequestSnapshot(
                ip: $request->ip(),
                userAgent: $request->userAgent(),
                host: $request->getHost(),
            );

            $batch = new IncomingBatch(events: [new IncomingEvent(
                type: EventType::Custom,
                occurredAt: CarbonImmutable::now(),
                name: $name,
                route: $request->route()?->getName(),
                url: $this->redactor->redact($request->fullUrl()),
                props: $props === [] ? null : $props,
                value: $value,
            )]);

            $action = $this->action;

            defer(function () use ($action, $uuid, $subject, $snapshot, $batch): void {
                try {
                    $action->execute($uuid, $subject, $snapshot, $batch);
                } catch (Throwable $e) {
                    Log::channel(config('analytics.log_channel'))->error('Analytics server event failed.', [
                        'exception' => $e,
                    ]);
                }
            });
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Analytics server event could not be recorded.', [
                'exception' => $e,
            ]);
        }
    }
}
