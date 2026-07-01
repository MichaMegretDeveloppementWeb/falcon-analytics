<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs;

/**
 * The minimal, serialisable slice of the request the deferred enrichment needs.
 * Captured synchronously in the controller and carried into the deferred action
 * so the heavy geo/device work never touches the Request on the response path.
 */
final readonly class RequestSnapshot
{
    public function __construct(
        public ?string $ip = null,
        public ?string $userAgent = null,
        public string $host = '',
    ) {}
}
