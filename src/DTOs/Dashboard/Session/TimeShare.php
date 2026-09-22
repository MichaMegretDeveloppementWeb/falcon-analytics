<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard\Session;

/**
 * The time a session spent on one page, or on all the others together.
 *
 * @internal
 */
final readonly class TimeShare
{
    /**
     * @param  string  $color  the series token that draws it
     */
    public function __construct(
        public string $label,
        public int $seconds,
        public string $duration,
        public string $color,
    ) {}
}
