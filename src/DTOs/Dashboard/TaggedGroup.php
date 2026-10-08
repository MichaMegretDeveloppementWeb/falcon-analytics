<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard;

/**
 * Sessions that arrived with the same parameters on the same day · one
 * session read from its row, with its visitor, or a day's group read from the
 * totals, with no visitor to name.
 *
 * @internal
 */
final readonly class TaggedGroup
{
    /**
     * @param  array<string, string>  $params
     */
    public function __construct(
        public array $params,
        public string $day,
        public ?int $visitorId,
        public int $sessions,
    ) {}
}
