<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Models\SearchConsoleConnection;
use Falcon\Analytics\Models\SearchQuery;
use Falcon\Analytics\Services\SearchConsole\SearchConsoleClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Daily Search Console sync: pulls the day+query rows since the last sync and
 * upserts them into the local cache. GSC data settles over ~3 days, so the
 * window re-reads those days on every run; the first run backfills the API's
 * full 16-month history in paginated calls. Inert without an attached
 * connection, so scheduling it unconditionally costs nothing.
 */
final class SyncSearchConsoleCommand extends Command
{
    /** GSC keeps rewriting the trailing days as data consolidates. */
    private const RESYNC_DAYS = 3;

    /** The API serves ~16 months of history; stay just inside. */
    private const BACKFILL_DAYS = 480;

    private const UPSERT_CHUNK = 500;

    protected $signature = 'analytics:search-console:sync';

    protected $description = 'Pull the organic search queries from Google Search Console into the local cache.';

    public function handle(SearchConsoleClient $client): int
    {
        $connection = SearchConsoleConnection::current();

        if ($connection === null || ! $connection->isConnected()) {
            $this->components->info('No attached Search Console connection; nothing to sync.');

            return self::SUCCESS;
        }

        $today = CarbonImmutable::today();
        $from = $connection->last_synced_at === null
            ? $today->subDays(self::BACKFILL_DAYS)
            : $connection->last_synced_at->toImmutable()->startOfDay()->subDays(self::RESYNC_DAYS);

        $this->components->info("Syncing {$connection->property} from {$from->toDateString()} to {$today->toDateString()}.");

        try {
            $rows = $client->queryRows($connection, $from, $today);

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
                'last_error' => mb_substr('sync: '.$e->getMessage(), 0, 255),
            ]);
            Log::channel(config('analytics.log_channel'))->error('SearchConsole.sync_failed', ['exception' => $e]);
            $this->components->error('Sync failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(count($rows).' rows upserted.');

        return self::SUCCESS;
    }
}
