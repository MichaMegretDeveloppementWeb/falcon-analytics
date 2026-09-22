<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The schema the migrations build, written down in full.
 *
 * **This is the net that made folding the migrations safe.** Twenty-two
 * migrations became ten, one per table describing its final state; nothing but
 * a line-for-line comparison of what the engine ends up holding could show that
 * the fold changed nothing it was not meant to change.
 *
 * `migrations-et-schema.md` asks for exactly this: "comparer le schéma avant et
 * après par les tables système du moteur · colonnes, types, nullabilité,
 * défauts, clés, index, contraintes. Le diff doit être vide, sauf ce qui était
 * l'objet du changement."
 *
 * The fixture was taken from the schema the twenty-two built, with the one
 * intended change applied to it — the eighteen instants leaving the type the
 * engine converts — and **written down before the fold**, so the test could be
 * seen failing on those eighteen lines first.
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

    /**
     * And the reading knows how to find something: an empty comparison would
     * otherwise pass for agreement.
     */
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
