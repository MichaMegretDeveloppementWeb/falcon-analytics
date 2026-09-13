<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs;

/** @internal */
final readonly class IncomingBatch
{
    /**
     * @param  list<IncomingEvent>  $events
     */
    public function __construct(
        public array $events,
        public ?string $referrer = null,
    ) {}
}
