<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers;

use Falcon\Analytics\Actions\IngestEventsAction;
use Falcon\Analytics\Analytics;
use Falcon\Analytics\DTOs\RequestSnapshot;
use Falcon\Analytics\Http\Requests\IngestBatchRequest;
use Falcon\Analytics\Services\VisitorIdentity;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Illuminate\Support\defer;

final class IngestController
{
    /**
     * Access is already gated by the route middleware. Only the visitor identity
     * (which queues a cookie onto this response) and the subject are resolved
     * synchronously; the heavy geo/device enrichment and every database write are
     * deferred, and the enrichment then runs only when a new session starts.
     */
    public function __invoke(
        IngestBatchRequest $request,
        VisitorIdentity $identity,
        Analytics $analytics,
        IngestEventsAction $action,
    ): Response {
        $batch = $request->toBatch();
        $visitorUuid = $identity->resolve($request, $analytics->consentGranted());
        $subject = $analytics->subject();
        $snapshot = new RequestSnapshot(
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            host: $request->getHost(),
        );

        // Deferred so the beacon returns immediately; analytics must never surface
        // an error, so a persistence failure is logged and swallowed.
        defer(function () use ($action, $visitorUuid, $subject, $snapshot, $batch): void {
            try {
                $action->execute($visitorUuid, $subject, $snapshot, $batch);
            } catch (Throwable $e) {
                Log::channel(config('analytics.log_channel'))->error('Analytics ingestion failed.', [
                    'exception' => $e,
                ]);
            }
        });

        return response()->noContent();
    }
}
