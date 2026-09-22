<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard\Visitor;

/**
 * The sessions a visitor opened from one channel.
 *
 * @internal
 */
final readonly class SourceShare
{
    /**
     * @param  string  $source  the channel as stored, which the view names
     * @param  int  $percent  of the visitor's sessions, rounded
     */
    public function __construct(
        public string $source,
        public int $sessions,
        public int $percent,
    ) {}
}
