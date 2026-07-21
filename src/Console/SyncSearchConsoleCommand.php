<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use Falcon\Analytics\Models\SearchConsoleConnection;
use Falcon\Analytics\Services\SearchConsole\SearchConsoleSynchronizer;
use Illuminate\Console\Command;
use Throwable;

/**
 * Daily Search Console sync (self-scheduled at 05:00): thin CLI wrapper
 * around the synchronizer, inert without an attached connection so
 * scheduling it unconditionally costs nothing. The integrations screen
 * offers the same sync on demand.
 */
final class SyncSearchConsoleCommand extends Command
{
    protected $signature = 'analytics:search-console:sync';

    protected $description = 'Pull the organic search queries from Google Search Console into the local cache.';

    public function handle(SearchConsoleSynchronizer $synchronizer): int
    {
        $connection = SearchConsoleConnection::current();

        if ($connection === null || ! $connection->isConnected()) {
            $this->components->info('No attached Search Console connection; nothing to sync.');

            return self::SUCCESS;
        }

        $this->components->info("Syncing {$connection->property}.");

        try {
            $count = $synchronizer->sync($connection);
        } catch (Throwable $e) {
            $this->components->error('Sync failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info($count.' rows upserted.');

        return self::SUCCESS;
    }
}
