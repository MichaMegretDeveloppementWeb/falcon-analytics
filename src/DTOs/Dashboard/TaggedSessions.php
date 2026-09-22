<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard;

use Falcon\Analytics\Models\Session;
use Illuminate\Database\Eloquent\Collection;

/**
 * The sessions of a period that arrived with parameters in their address, as
 * far as the ceiling allows, and whether there were more.
 *
 * @internal
 */
final readonly class TaggedSessions
{
    /**
     * @param  Collection<int, Session>  $rows
     */
    public function __construct(
        public Collection $rows,
        public int $ceiling,
        public bool $truncated,
    ) {}
}
