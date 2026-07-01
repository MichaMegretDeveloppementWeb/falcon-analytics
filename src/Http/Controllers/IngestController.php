<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers;

use Falcon\Analytics\Actions\IngestEventsAction;
use Falcon\Analytics\Http\Requests\IngestBatchRequest;
use Falcon\Analytics\Services\IngestionContextResolver;
use Illuminate\Http\Response;

use function Illuminate\Support\defer;

final class IngestController
{
    /**
     * Access is already gated by the route middleware. Resolve the context
     * synchronously (it queues the visitor cookie onto this response) and defer
     * the database writes so the beacon returns immediately.
     */
    public function __invoke(
        IngestBatchRequest $request,
        IngestionContextResolver $resolver,
        IngestEventsAction $action,
    ): Response {
        $batch = $request->toBatch();
        $context = $resolver->resolve($request, $batch);

        defer(fn () => $action->execute($context, $batch));

        return response()->noContent();
    }
}
