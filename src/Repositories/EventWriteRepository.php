<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;
use Illuminate\Database\Eloquent\Builder;

/** @internal */
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
     * Erase the ANONYMOUS page views and clicks older than the cutoff, in
     * bounded batches so a single statement never locks the largest table.
     *
     * What is kept is what a screen still reads. An anonymous page view or
     * click is already counted into the daily summary, so erasing it costs no
     * figure. A row carrying a NAME feeds the events screen, the funnels and
     * the marketing conversions, which no summary can stand in for exactly, so
     * it stays whatever its age.
     *
     * Page views on a route a declared funnel steps through stay too: a
     * funnel's progression is sequential inside its window, which no daily
     * count can rebuild.
     *
     * @param  list<string>  $keptRoutes  routes a declared funnel steps through
     * @return int the number erased
     */
    public function pruneAnonymousOlderThan(CarbonImmutable $cutoff, array $keptRoutes = [], int $batchSize = 1000): int
    {
        $deleted = 0;

        do {
            $ids = Event::query()
                ->where('occurred_at', '<', $cutoff)
                ->whereIn('type', [EventType::Pageview->value, EventType::Click->value])

                // An empty name is no name: the collector writes one for a click on an element carrying nothing.
                ->where(fn (Builder $query): Builder => $query->whereNull('name')->orWhere('name', ''))

                ->when($keptRoutes !== [], fn (Builder $query): Builder => $query->where(
                    fn (Builder $inner): Builder => $inner
                        ->whereNull('route')
                        ->orWhereNotIn('route', $keptRoutes)
                ))
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
