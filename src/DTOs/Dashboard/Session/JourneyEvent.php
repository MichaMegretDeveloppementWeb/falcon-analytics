<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard\Session;

use Carbon\CarbonImmutable;

/**
 * A click or a named event, nested under the page it happened on.
 *
 * @internal
 */
final readonly class JourneyEvent
{
    public function __construct(
        public bool $isConversion,
        public string $label,
        public CarbonImmutable $occurredAt,
    ) {}
}
