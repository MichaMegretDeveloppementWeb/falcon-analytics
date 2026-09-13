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
 *
 * @internal
 */
final class SyncSearchConsoleCommand extends Command
{
    protected $signature = 'analytics:search-console:sync';

    protected $description = 'Tire les requêtes organiques de Google Search Console dans le cache local.';

    public function handle(SearchConsoleSynchronizer $synchronizer): int
    {
        $connection = SearchConsoleConnection::current();

        if ($connection === null || ! $connection->isConnected()) {
            $this->components->info('Aucune connexion Search Console rattachée : rien à synchroniser.');

            return self::SUCCESS;
        }

        $this->components->info("Synchronisation de {$connection->property}.");

        try {
            $count = $synchronizer->sync($connection);
        } catch (Throwable $e) {
            $this->components->error('La synchronisation a échoué : '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info($count.' rows upserted.');

        return self::SUCCESS;
    }
}
