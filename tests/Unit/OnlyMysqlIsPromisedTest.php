<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Unit;

use Falcon\Analytics\Support\DatabaseEngine;
use Falcon\Analytics\Tests\Fixtures\ExposedSessionQueries;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * One engine is promised, MySQL, and the code claims nothing more.
 *
 * MariaDB follows MySQL with no code of its own · `DATE`, `DATE_FORMAT` and
 * `TIMESTAMPDIFF` carry the same name and meaning there. Any other engine is
 * refused up front: a fresh Laravel application defaults to SQLite, which would
 * otherwise fail later, on a screen, with a syntax error nobody links to this package.
 *
 * No application boots here · both methods take the driver as an argument.
 */
final class OnlyMysqlIsPromisedTest extends TestCase
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

    #[DataProvider('engines')]
    public function test_it_accepts_only_what_the_suite_runs(string $driver, bool $expected): void
    {
        $this->assertSame($expected, DatabaseEngine::isSupported($driver));
    }

    /** @return list<array{string, bool}> */
    public static function engines(): array
    {
        return [
            ['mysql', true],
            ['mariadb', true],
            ['sqlite', false],
            ['pgsql', false],
            ['sqlsrv', false],
        ];
    }

    /**
     * "MySQL is required" alone leaves the reader looking, and the answer is
     * almost always a `DB_CONNECTION` nobody thought of.
     */
    public function test_the_refusal_names_the_engine_it_found(): void
    {
        $refusal = DatabaseEngine::refusal('sqlite');

        $this->assertStringContainsString('sqlite', $refusal);
        $this->assertStringContainsString('MySQL ou MariaDB', $refusal);
        $this->assertStringContainsString('DB_CONNECTION', $refusal);
    }
}
