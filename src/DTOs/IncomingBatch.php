<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs;

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
