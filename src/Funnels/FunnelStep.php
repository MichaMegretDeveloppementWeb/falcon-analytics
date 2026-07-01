<?php

declare(strict_types=1);

namespace Falcon\Analytics\Funnels;

/**
 * One step of a funnel: a weighted milestone matched either by a named event
 * (a data-track-event) or by a pageview route. The value is the step's weight.
 */
final readonly class FunnelStep
{
    public function __construct(
        public string $label,
        public float $value,
        public ?string $event = null,
        public ?string $route = null,
    ) {}
}
