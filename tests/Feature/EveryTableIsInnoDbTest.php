<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Every table of the package is InnoDB, whatever the server would lay down: a
 * MyISAM table keeps neither its foreign keys nor its transactions, and says
 * nothing about it.
 *
 * Outside `RefreshDatabase`: the tables are rebuilt, and the connection is
 * replaced on the way.
 */
final class EveryTableIsInnoDbTest extends TestCase
{
    /**
     * The bench's connection names InnoDB itself, so it is taken off for the
     * replay, and the session's default turned to MyISAM.
     */
    public function test_every_table_is_innodb_on_a_server_that_defaults_to_another_engine(): void
    {
        $connection = (string) config('database.default');
        $engine = config("database.connections.{$connection}.engine");

        try {
            config()->set("database.connections.{$connection}.engine", null);
            DB::purge($connection);
            DB::statement("SET SESSION default_storage_engine = 'MyISAM'");

            $this->artisan('analytics:refresh', ['--force' => true])->assertSuccessful()->run();

            $engines = $this->enginesOfThePackage();
        } finally {
            config()->set("database.connections.{$connection}.engine", $engine);
            DB::purge($connection);
            $this->artisan('analytics:refresh', ['--force' => true])->run();
        }

        $this->assertCount(12, $engines, 'The engine of every table was not read: this test would prove nothing.');
        $this->assertSame([], array_keys(array_filter($engines, static fn (mixed $name): bool => $name !== 'InnoDB')));
    }

    /** A table restored or copied onto another engine is named by the diagnostic. */
    public function test_the_diagnostic_names_a_table_laid_on_another_engine(): void
    {
        DB::statement('CREATE TABLE falcon_analytics_restored_copy (id INT PRIMARY KEY) ENGINE = MyISAM');

        try {
            $this->artisan('analytics:check')
                ->expectsOutputToContain('Ces tables du paquet ne sont pas en InnoDB · falcon_analytics_restored_copy.')
                ->assertFailed()
                ->run();
        } finally {
            DB::statement('DROP TABLE falcon_analytics_restored_copy');
        }
    }

    /** @return array<string, mixed> */
    private function enginesOfThePackage(): array
    {
        return DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', 'like', 'falcon\_analytics\_%')
            ->where('TABLE_TYPE', 'BASE TABLE')
            ->pluck('ENGINE', 'TABLE_NAME')
            ->all();
    }
}
