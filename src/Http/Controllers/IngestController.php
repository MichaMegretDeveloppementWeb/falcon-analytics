<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers;

use Closure;
use Falcon\Analytics\Actions\IngestEventsAction;
use Falcon\Analytics\Analytics;
use Falcon\Analytics\DTOs\RequestSnapshot;
use Falcon\Analytics\Http\Requests\IngestBatchRequest;
use Falcon\Analytics\Services\PageContextSealer;
use Falcon\Analytics\Services\VisitorIdentityResolver;
use Falcon\Analytics\Support\AfterTheResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/** @internal what is promised is the route name, never this class. */
final class IngestController
{
    /** A refused context is said once in this many seconds: the route lets one address send 120 batches a minute. */
    private const REFUSAL_LOGGED_EVERY = 600;

    /**
     * Access is already gated by the route middleware. Only the visitor identity
     * (which queues a cookie onto this response) and the subject are resolved
     * synchronously; the heavy geo/device enrichment and every database write are
     * deferred, and the enrichment then runs only when a new session starts.
     *
     * A batch whose page context names someone the host's session no longer
     * vouches for is what a page sent on its way out, after a sign-out · it is
     * attached by the context alone, and never resolves an identity here.
     */
    public function __invoke(
        IngestBatchRequest $request,
        VisitorIdentityResolver $identity,
        Analytics $analytics,
        PageContextSealer $contexts,
        IngestEventsAction $action,
    ): Response {
        $batch = $request->toBatch();
        $sealed = $request->pageContext();
        $context = $sealed === null ? null : $contexts->open($sealed);
        $subject = $analytics->subject();

        if ($sealed !== null && $context === null) {
            $this->sayTheContextWasRefused();

            return response()->noContent();
        }

        if ($context !== null && ! $context->names($subject)) {
            $this->afterTheResponse(fn () => $action->executeLeftover($context, $batch));

            return response()->noContent();
        }

        $visitorUuid = $identity->resolve($request, $analytics->hasConsent());
        $snapshot = new RequestSnapshot(
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            host: $request->getHost(),
        );

        $this->afterTheResponse(fn () => $action->execute($visitorUuid, $subject, $snapshot, $batch));

        return response()->noContent();
    }

    /**
     * Deferred so the beacon returns immediately; analytics must never surface
     * an error, so a persistence failure is logged and swallowed.
     */
    private function afterTheResponse(Closure $ingestion): void
    {
        AfterTheResponse::run(function () use ($ingestion): void {
            try {
                $ingestion();
            } catch (Throwable $e) {
                Log::channel(config('analytics.log_channel'))->error('Analytics ingestion failed.', [
                    'exception' => $e,
                ]);
            }
        });
    }

    /** Never the context itself: whoever forged it does not get it echoed into the log. */
    private function sayTheContextWasRefused(): void
    {
        try {
            if (Cache::add('falcon-analytics:context-refused', true, self::REFUSAL_LOGGED_EVERY)) {
                Log::channel(config('analytics.log_channel'))->warning(
                    'Analytics batch dropped: its page context could not be read. Altered, sealed with another key, or not one the package wrote.',
                );
            }
        } catch (Throwable) {
            // The batch is dropped either way · a cache that cannot answer only costs the warning.
        }
    }
}
