<?php

declare(strict_types=1);

namespace Falcon\Analytics\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the package's tables and replays its migrations. Nothing else is
 * touched.
 *
 * **Why a command and not a written procedure** · `migrate:fresh` would carry
 * away the host's own tables, which are none of the package's business, and a
 * procedure to improvise by hand is a procedure someone gets wrong on the day
 * it matters.
 *
 * **It designates its tables by the prefix**, read from the engine's own
 * catalogue — never from a list kept by hand, which one would have to remember
 * to update. A table added tomorrow is covered without anyone thinking about
 * it.
 *
 * **It does not refuse to run outside development**, unlike a command that
 * writes made-up data: this one exists precisely for a host already in
 * production. What holds it is that it names every table, with its row count,
 * and waits for a yes.
 *
 * @internal
 */
final class RefreshCommand extends Command
{
    protected $signature = 'analytics:refresh {--force : Ne pas demander confirmation}';

    protected $description = 'Supprime les tables de l’analytique et rejoue ses migrations. Les statistiques repartent de zéro.';

    public function handle(): int
    {
        $tables = $this->tablesOfThePackage();

        if ($tables === []) {
            $this->components->info('Aucune table de l’analytique : les migrations vont simplement être jouées.');

            return $this->replay();
        }

        $this->components->warn('Les tables suivantes vont être supprimées, avec tout ce qu’elles contiennent.');
        $this->table(['Table', 'Lignes'], array_map(
            fn (string $table): array => [$table, (string) DB::table($table)->count()],
            $tables,
        ));

        if (! $this->option('force') && ! $this->confirm('Supprimer ces tables et rejouer les migrations ?')) {
            $this->components->info('Rien n’a été touché.');

            return self::SUCCESS;
        }

        $this->drop($tables);
        $this->forgetTheMigrations();

        return $this->replay(count($tables));
    }

    /**
     * The package's tables, and only those.
     *
     * The database is named: a listing that does not name it returns every
     * database of the server, and a list too wide feeding a drop does not
     * forgive.
     *
     * @return list<string>
     */
    private function tablesOfThePackage(): array
    {
        $connection = DB::connection();

        $tables = array_filter(
            Schema::getTableListing($connection->getDatabaseName(), schemaQualified: false),
            fn (string $table): bool => str_starts_with($table, 'falcon_analytics_'),
        );

        // `sort()` reindexes, so what comes out is a list.
        sort($tables);

        return $tables;
    }

    /**
     * @param  list<string>  $tables
     */
    private function drop(array $tables): void
    {
        // The tables reference one another, and the order that would satisfy
        // every constraint is one more thing to get wrong. Constraints are put
        // back whatever happens.
        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                Schema::drop($table);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * Forgets the package's migrations, and only those, so the migrator plays
     * them again and leaves the host's alone.
     *
     * The rows are matched on the file names the package actually ships, read
     * from its own directory: a name pattern would also catch a host migration
     * that happened to mention the package.
     */
    private function forgetTheMigrations(): void
    {
        $files = glob(dirname(__DIR__, 2).'/database/migrations/*.php');

        $names = array_map(
            static fn (string $path): string => basename($path, '.php'),
            $files === false ? [] : $files,
        );

        if ($names !== []) {
            DB::table('migrations')->whereIn('migration', $names)->delete();
        }
    }

    private function replay(int $dropped = 0): int
    {
        $this->call('migrate', ['--force' => true]);

        $this->components->info($dropped === 0
            ? 'Migrations de l’analytique jouées.'
            : "{$dropped} table(s) supprimée(s), migrations de l’analytique rejouées. Les statistiques repartent de zéro.");

        return self::SUCCESS;
    }
}
