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

    /** Whether one of its events becomes a row · a batch of heartbeats only keeps a session alive. */
    public function storesSomething(): bool
    {
        foreach ($this->events as $event) {
            if ($event->type->isStored()) {
                return true;
            }
        }

        return false;
    }
}
