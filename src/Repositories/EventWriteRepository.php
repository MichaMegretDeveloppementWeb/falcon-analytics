<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories;

use Falcon\Analytics\Models\Event;

final readonly class EventWriteRepository
{
    /**
     * Bulk-insert already-mapped event rows. Bypasses model events for speed on
     * the ingestion hot path, so rows must be DB-ready (serialised values).
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function insertBatch(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        Event::query()->insert($rows);
    }
}
