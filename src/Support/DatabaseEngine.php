<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

use Falcon\Analytics\Repositories\Concerns\ScopesSessionQueries;
use Illuminate\Database\Connection;
use Illuminate\Database\MySqlConnection;

/**
 * The database engine behind a connection, as its server names it, and whether
 * the package runs on it.
 *
 * MySQL from 8.0.16, the first to enforce the schema's `CHECK` constraints, and
 * MariaDB from 10.11. The dashboards need three bits of raw SQL that Eloquent
 * cannot express — a day bucket, a minute bucket and a duration in seconds —
 * written in MySQL's dialect, which MariaDB shares; see {@see ScopesSessionQueries}.
 *
 * Read from the server and not from the driver alone: MariaDB answers to the
 * `mysql` driver as often as to its own, and the floor that matters is the
 * server's version. Any other engine, or a version under the floor, is refused
 * before the installer writes anything.
 *
 * @internal
 */
final readonly class DatabaseEngine
{
    private const string MYSQL_FLOOR = '8.0.16';

    private const string MARIADB_FLOOR = '10.11';

    public function __construct(
        public string $driver,
        public ?string $version = null,
        public bool $isMaria = false,
    ) {}

    public static function of(Connection $connection): self
    {
        if ($connection instanceof MySqlConnection) {
            return new self($connection->getDriverName(), self::numbersOf($connection->getServerVersion()), $connection->isMaria());
        }

        return new self($connection->getDriverName());
    }

    /** MySQL or MariaDB, at or above its floor. */
    public function isSupported(): bool
    {
        if ($this->version === null || ! in_array($this->driver, ['mysql', 'mariadb'], true)) {
            return false;
        }

        return version_compare($this->version, $this->floor(), '>=');
    }

    /** The engine and its version, as a person names them. */
    public function inWords(): string
    {
        if ($this->version === null) {
            return "« {$this->driver} »";
        }

        return ($this->isMaria ? 'MariaDB ' : 'MySQL ').$this->version;
    }

    /**
     * Why the package refuses this engine, and what to change, or null when it
     * does not.
     *
     * It names the engine found, not only the ones expected: the cause is
     * almost always a `DB_CONNECTION` nobody thought about.
     */
    public function refusal(): ?string
    {
        if ($this->isSupported()) {
            return null;
        }

        $wanted = 'MySQL '.self::MYSQL_FLOOR.' ou plus, ou MariaDB '.self::MARIADB_FLOOR.' ou plus';

        if ($this->version === null) {
            return "La base est en {$this->inWords()}, et le paquet demande {$wanted}. Ses écrans posent du SQL propre à ce moteur. Réglez DB_CONNECTION, puis relancez.";
        }

        return "La base est {$this->inWords()}, sous la version que le paquet demande · {$wanted}. Mettez le serveur à jour, puis relancez.";
    }

    private function floor(): string
    {
        return $this->isMaria ? self::MARIADB_FLOOR : self::MYSQL_FLOOR;
    }

    /** « 8.0.36-0ubuntu0.22.04.1 » read as « 8.0.36 ». */
    private static function numbersOf(string $version): ?string
    {
        return preg_match('/^\d+(?:\.\d+){0,2}/', $version, $numbers) === 1 ? $numbers[0] : null;
    }
}
