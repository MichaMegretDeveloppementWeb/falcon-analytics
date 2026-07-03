<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories;

use Carbon\CarbonImmutable;
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

    /**
     * Delete raw events older than the cutoff, in bounded batches so a single
     * statement never locks the largest table. Returns the number deleted.
     */
    public function pruneOlderThan(CarbonImmutable $cutoff, int $batchSize = 1000): int
    {
        $deleted = 0;

        do {
            $ids = Event::query()
                ->where('occurred_at', '<', $cutoff)
                ->limit($batchSize)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += Event::query()->whereKey($ids->all())->delete();
        } while ($ids->count() === $batchSize);

        return $deleted;
    }
}
