<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The schema the migrations build, written down in full.
 *
 * What the engine ends up holding — columns, types, nullability, defaults,
 * indexes, foreign keys — is read from its own tables and compared line for line
 * with the fixture. The difference must be empty, save what a change was meant
 * to change.
 *
 * **When a migration legitimately changes the schema**, this fixture is
 * rewritten in the same commit. It is not a chore: it is the one place a
 * reviewer sees what the schema became.
 */
final class TheSchemaIsExactlyWhatItSaysTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_migrations_build_exactly_the_schema_that_is_written_down(): void
    {
        $expected = $this->writtenDown();
        $actual = $this->schemaOfThePackage();

        $this->assertSame(
            $expected,
            $actual,
            "Le schéma bâti par les migrations n'est plus celui qui est écrit dans tests/Fixtures/schema.txt.\n"
            .'En trop : '.implode(', ', array_diff($actual, $expected))."\n"
            .'Manquant : '.implode(', ', array_diff($expected, $actual)),
        );
    }

    /** An empty reading would otherwise pass for agreement. */
    public function test_the_reading_sees_a_schema_that_is_really_there(): void
    {
        $actual = $this->schemaOfThePackage();

        $this->assertContains('COL falcon_analytics_sessions.started_at datetime null=NO def=- extra=', $actual);
        $this->assertContains('IDX falcon_analytics_sessions.PRIMARY unique=oui (id)', $actual);
        $this->assertContains('FK falcon_analytics_events.falcon_analytics_events_session_id_foreign (session_id) -> falcon_analytics_sessions.id del=CASCADE upd=NO ACTION', $actual);
        $this->assertGreaterThan(100, count($actual));
    }

    /**
     * @return list<string>
     */
    private function writtenDown(): array
    {
        $lines = file(__DIR__.'/../Fixtures/schema.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        // `file()` already returns a list, whatever the flags.
        return $lines === false ? [] : $lines;
    }
}
