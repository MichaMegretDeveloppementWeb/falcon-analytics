<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `analytics:refresh` empties the package and leaves the host alone.
 *
 * **This is the whole point of the command.** `migrate:fresh` would carry away
 * the host's own tables, which is exactly what a host upgrading the package
 * cannot afford — so the one thing worth proving is that a table standing next
 * to the package's comes out untouched, with its rows.
 */
final class TheRefreshTouchesOnlyTheAnalyticsTablesTest extends TestCase
{
    use RefreshDatabase;

    private const A_TABLE_OF_THE_HOST = 'boutique_commandes';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create(self::A_TABLE_OF_THE_HOST, function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 20);
        });

        DB::table(self::A_TABLE_OF_THE_HOST)->insert([
            ['reference' => 'BC-001'],
            ['reference' => 'BC-002'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists(self::A_TABLE_OF_THE_HOST);

        parent::tearDown();
    }

    public function test_it_empties_the_package_and_leaves_the_host_table_with_its_rows(): void
    {
        $this->aSessionExists();

        $this->assertSame(1, Session::query()->count());

        $this->artisan('analytics:refresh', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, Session::query()->count(), 'Les tables du paquet repartent de zéro.');
        $this->assertSame(
            2,
            DB::table(self::A_TABLE_OF_THE_HOST)->count(),
            'La table de l’hôte doit sortir intacte, avec ses lignes.',
        );
    }

    public function test_it_leaves_the_schema_exactly_as_a_fresh_installation_builds_it(): void
    {
        $this->aSessionExists();

        $this->artisan('analytics:refresh', ['--force' => true])->assertSuccessful();

        // The written-down schema of a fresh installation, as TheSchemaIsExactlyWhatItSaysTest reads it.
        $expected = file(__DIR__.'/../Fixtures/schema.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $this->assertNotFalse($expected);
        $this->assertSame($expected, $this->schemaOfThePackage());
    }

    public function test_it_forgets_only_its_own_migrations(): void
    {
        $ofTheHost = DB::table('migrations')
            ->where('migration', 'not like', '%falcon_analytics%')
            ->where('migration', 'not like', '%analytics_tables%')
            ->count();

        $this->assertGreaterThan(0, $ofTheHost, 'Le banc doit porter des migrations qui ne sont pas du paquet.');

        $this->artisan('analytics:refresh', ['--force' => true])->assertSuccessful();

        $this->assertSame(
            $ofTheHost,
            DB::table('migrations')
                ->where('migration', 'not like', '%falcon_analytics%')
                ->where('migration', 'not like', '%analytics_tables%')
                ->count(),
            'Le journal ne doit perdre que les migrations du paquet.',
        );
    }

    /** Refusing without a yes is what holds a command that drops tables. */
    public function test_it_touches_nothing_when_the_answer_is_no(): void
    {
        $this->aSessionExists();

        $this->artisan('analytics:refresh')
            ->expectsConfirmation('Supprimer ces tables et rejouer les migrations ?', 'no')
            ->assertSuccessful();

        $this->assertSame(1, Session::query()->count(), 'Un refus ne doit rien supprimer.');
    }

    private function aSessionExists(): void
    {
        Session::factory()->create(['pageview_count' => 1]);
    }
}
