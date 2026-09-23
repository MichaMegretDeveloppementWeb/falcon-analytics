<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

use Falcon\Analytics\Repositories\Concerns\ScopesSessionQueries;
use Illuminate\Support\Facades\DB;

/**
 * The database engines the package is built and tested for, and nothing else.
 *
 * MySQL and MariaDB. The dashboards need three bits of raw SQL that Eloquent
 * cannot express — a day bucket, a minute bucket and a duration in seconds —
 * written in MySQL's dialect, which MariaDB shares; see {@see ScopesSessionQueries}.
 * No other engine is announced, since the test suite runs on no other.
 *
 * Checked up front because a fresh Laravel arrives configured for SQLite: an
 * install that said nothing would publish, migrate, and leave the host to meet
 * a SQL syntax error much later, on a screen, with nothing tying it to this
 * package.
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
     * It names the driver found, not only the ones expected: the cause is
     * almost always a `DB_CONNECTION` nobody thought about.
     */
    public static function refusal(?string $driver = null): string
    {
        $found = $driver ?? self::current();

        return "La connexion de base de données est en « {$found} », et le paquet demande MySQL ou MariaDB. "
            .'Ses écrans posent du SQL propre à ce moteur. Réglez DB_CONNECTION, puis relancez.';
    }
}
