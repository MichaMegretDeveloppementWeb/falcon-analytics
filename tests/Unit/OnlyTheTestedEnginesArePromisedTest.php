<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Unit;

use Falcon\Analytics\Support\DatabaseEngine;
use Falcon\Analytics\Tests\Fixtures\ExposedSessionQueries;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * MySQL and MariaDB are promised, each from the floor the suite runs on, and
 * the code claims nothing more.
 *
 * MariaDB follows MySQL with no code of its own · `DATE`, `DATE_FORMAT` and
 * `TIMESTAMPDIFF` carry the same name and meaning there. Any other engine is
 * refused up front: a fresh Laravel application defaults to SQLite, which would
 * otherwise fail later, on a screen, with a syntax error nobody links to this package.
 *
 * No application boots here · the engine is built from what a server answers.
 */
final class OnlyTheTestedEnginesArePromisedTest extends TestCase
{
    public function test_the_three_expressions_are_written_in_mysql(): void
    {
        $expressions = new ExposedSessionQueries;

        $this->assertSame('DATE(started_at)', $expressions->day());
        $this->assertSame("DATE_FORMAT(occurred_at, '%Y-%m-%d %H:%i')", $expressions->minute());
        $this->assertSame('TIMESTAMPDIFF(SECOND, started_at, last_activity_at)', $expressions->duration());
    }

    /** This keeps a branch for another engine from coming back without the promise following. */
    public function test_no_other_dialect_survives_in_the_expressions(): void
    {
        $expressions = new ExposedSessionQueries;

        $written = implode(' ', [
            $expressions->day(),
            $expressions->minute(),
            $expressions->duration(),
        ]);

        // SQL Server's `FORMAT(` is left out: MySQL's `DATE_FORMAT` contains it.
        foreach (['to_char', 'strftime', 'CONVERT(', 'DATEDIFF', 'EXTRACT(EPOCH'] as $ailleurs) {
            $this->assertStringNotContainsString($ailleurs, $written);
        }
    }

    /** @return array<string, array{DatabaseEngine}> */
    public static function supported(): array
    {
        return [
            'MySQL at its floor' => [new DatabaseEngine('mysql', '8.0.16')],
            'MySQL 8.4' => [new DatabaseEngine('mysql', '8.4.2')],
            'MariaDB at its floor' => [new DatabaseEngine('mariadb', '10.11', isMaria: true)],
            'MariaDB 11.4' => [new DatabaseEngine('mariadb', '11.4.2', isMaria: true)],
            'MariaDB behind the mysql driver' => [new DatabaseEngine('mysql', '10.11.6', isMaria: true)],
        ];
    }

    #[DataProvider('supported')]
    public function test_an_engine_at_or_above_its_floor_is_supported(DatabaseEngine $engine): void
    {
        $this->assertTrue($engine->isSupported());
        $this->assertNull($engine->refusal());
    }

    /** @return array<string, array{DatabaseEngine, non-empty-string}> */
    public static function underTheFloor(): array
    {
        return [
            'MySQL just under its floor' => [new DatabaseEngine('mysql', '8.0.15'), 'La base est MySQL 8.0.15'],
            'MySQL 5.7' => [new DatabaseEngine('mysql', '5.7.44'), 'La base est MySQL 5.7.44'],
            'MariaDB 10.6, above the floor of MySQL' => [new DatabaseEngine('mariadb', '10.6.18', isMaria: true), 'La base est MariaDB 10.6.18'],
        ];
    }

    /** @param non-empty-string $named */
    #[DataProvider('underTheFloor')]
    public function test_a_version_under_the_floor_is_refused_by_its_name(DatabaseEngine $engine, string $named): void
    {
        $this->assertFalse($engine->isSupported());
        $this->assertStringStartsWith($named, (string) $engine->refusal());
        $this->assertStringContainsString('MySQL 8.0.16 ou plus, ou MariaDB 10.11 ou plus', (string) $engine->refusal());
    }

    /** @return array<string, array{string}> */
    public static function otherEngines(): array
    {
        return ['SQLite' => ['sqlite'], 'PostgreSQL' => ['pgsql'], 'SQL Server' => ['sqlsrv']];
    }

    /**
     * "MySQL is required" alone leaves the reader looking, and the answer is
     * almost always a `DB_CONNECTION` nobody thought of.
     */
    #[DataProvider('otherEngines')]
    public function test_another_engine_is_refused_by_its_driver(string $driver): void
    {
        $engine = new DatabaseEngine($driver);

        $this->assertFalse($engine->isSupported());
        $this->assertSame(
            "La base est en « {$driver} », et le paquet demande MySQL 8.0.16 ou plus, ou MariaDB 10.11 ou plus. Ses écrans posent du SQL propre à ce moteur. Réglez DB_CONNECTION, puis relancez.",
            $engine->refusal(),
        );
    }
}
