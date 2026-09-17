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
     * **What is kept is what a screen still reads**, and that is the whole
     * design of the retention · an anonymous page view or click has already
     * been counted into the daily summary, so erasing it costs no figure. A row
     * carrying a NAME has not: it feeds the events screen, the funnels and the
     * marketing conversions, none of which a summary could stand in for
     * exactly — so those rows stay, whatever their age.
     *
     * **Page views on a route a declared funnel steps through stay too.** A
     * funnel's progression is sequential inside its window, which no daily
     * count can rebuild; keeping the handful of routes it names is what keeps
     * those screens exact at any depth.
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

                // A name is what makes a row worth keeping: an empty string is
                // not a name, and the collector writes one for a click whose
                // element carried nothing.
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
