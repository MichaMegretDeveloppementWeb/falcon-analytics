<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\SearchConsole;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Models\SearchConsoleConnection;
use Falcon\Analytics\Models\SearchQuery;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pulls the day+query rows from the Search Console API into the local cache,
 * shared by the daily command and the manual "sync now" action. GSC data
 * settles over ~3 days, so the window re-reads those days on every run; the
 * first run backfills the API's full 16-month history. On failure the
 * connection is flagged (surfaced on the dashboard) and the error rethrown
 * for the caller to report.
 */
final class SearchConsoleSynchronizer
{
    /** GSC keeps rewriting the trailing days as data consolidates. */
    private const RESYNC_DAYS = 3;

    /** The API serves ~16 months of history; stay just inside. */
    private const BACKFILL_DAYS = 480;

    private const UPSERT_CHUNK = 500;

    /** The `last_error` column is varchar(255). */
    private const ERROR_COLUMN_LIMIT = 255;

    public function __construct(private readonly SearchConsoleClient $client) {}

    /**
     * Sync the connection's attached property and return the number of rows
     * written.
     */
    public function sync(SearchConsoleConnection $connection): int
    {
        $today = CarbonImmutable::today();
        $from = $connection->last_synced_at === null
            ? $today->subDays(self::BACKFILL_DAYS)
            : $connection->last_synced_at->toImmutable()->startOfDay()->subDays(self::RESYNC_DAYS);

        try {
            $rows = $this->client->queryRows($connection, $from, $today);

            foreach (array_chunk($rows, self::UPSERT_CHUNK) as $chunk) {
                SearchQuery::query()->upsert($chunk, ['date', 'query'], ['clicks', 'impressions', 'position']);
            }

            $connection->update([
                'last_synced_at' => now(),
                'status' => SearchConsoleConnection::STATUS_CONNECTED,
                'last_error' => null,
            ]);
        } catch (Throwable $e) {
            $connection->update([
                'status' => SearchConsoleConnection::STATUS_ERROR,
                'last_error' => mb_substr('sync: '.$e->getMessage(), 0, self::ERROR_COLUMN_LIMIT),
            ]);
            Log::channel(config('analytics.log_channel'))->error('SearchConsole.sync_failed', ['exception' => $e]);

            throw $e;
        }

        return count($rows);
    }
}
