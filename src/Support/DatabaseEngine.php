<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

use Falcon\Analytics\Repositories\Concerns\ScopesSessionQueries;
use Illuminate\Support\Facades\DB;

/**
 * The database engines the package is built and tested for, and nothing else.
 *
 * **MySQL and MariaDB.** The dashboards need three bits of raw SQL that Eloquent
 * cannot express — a day bucket, a minute bucket and a duration in seconds —
 * and those are written in MySQL's dialect. MariaDB writes them identically, so
 * it costs not one line; see {@see ScopesSessionQueries}.
 *
 * Nothing else is announced, because nothing else is run. The code carried arms
 * for PostgreSQL, SQL Server and SQLite that no test ever exercised: three
 * engines promised by the code, three by the notice, one by the suite, and no
 * two lists the same.
 *
 * **Why this is guarded rather than left to fail** · a fresh Laravel arrives
 * configured for SQLite. An install that says nothing would publish the config,
 * publish the compiled files, write the environment and migrate — and the host
 * would meet the defect much later, on a screen, through a SQL syntax error
 * nobody connects to this package.
 *
 * @internal
 */
final class DatabaseEngine
{
    /** @var list<string> */
    public const SUPPORTED = ['mysql', 'mariadb'];

    /** The driver of the connection the package's models actually use. */
    public static function current(): string
    {
        return DB::connection()->getDriverName();
    }

    public static function isSupported(?string $driver = null): bool
    {
        return in_array($driver ?? self::current(), self::SUPPORTED, true);
    }

    /**
     * What to tell whoever is holding the wrong engine.
     *
     * It names the driver found rather than only the ones expected: « MySQL is
     * required » leaves the reader looking for where to check, and the answer is
     * almost always a `DB_CONNECTION` nobody thought about.
     */
    public static function refusal(?string $driver = null): string
    {
        $found = $driver ?? self::current();

        return "La connexion de base de données est en « {$found} », et le paquet demande MySQL ou MariaDB. "
            .'Ses écrans posent du SQL propre à ce moteur. Réglez DB_CONNECTION, puis relancez.';
    }
}
