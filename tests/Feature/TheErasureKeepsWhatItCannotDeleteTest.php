<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Livewire\Admin\VisitorDetailPage;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * Erasing a visitor fails, and the screen says so without losing anything.
 *
 * **A file of its own, and for a mechanical reason.** `ForgetVisitorAction`
 * deletes inside a transaction, deliberately, to behave the same way on every
 * engine. Breaking a table needs DDL, and a DDL statement implicitly commits
 * the transaction the bench holds open: the savepoints go with it, and the code
 * fails on "SAVEPOINT trans2 does not exist" instead of failing on its
 * deletion. That is no longer the same thing being measured.
 *
 * This class therefore keeps `RefreshDatabase` for the migration and
 * **neutralises its transactional wrapper**: the action's transaction is then a
 * real one, and the rename disturbs nothing. The price is having to tidy up
 * afterwards, which `tearDown` does.
 *
 * Two other routes were tried and set aside, so nobody takes them up again.
 * Shifting the table prefix does not work here: Livewire rehydrates the visitor
 * on every interaction, so the read would break before the write and the test
 * would pass for the wrong reason. And `DatabaseMigrations` does not mount the
 * fixture migrations under Testbench, so the administrators table is missing
 * before the first call even happens.
 */
final class TheErasureKeepsWhatItCannotDeleteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The bench's transactional wrapper, removed.
     *
     * `RefreshDatabase` migrates then opens a transaction it rolls back at the
     * end, which makes every test free. Here, that transaction is precisely
     * what prevents measuring what we want.
     */
    public function beginDatabaseTransaction(): void
    {
        //
    }

    protected function tearDown(): void
    {
        // Nothing rolls back on its own: what this test wrote is deleted here,
        // otherwise it would leave it to the next one.
        if (Schema::hasTable('falcon_analytics_visitors')) {
            DB::table('falcon_analytics_visitors')->delete();
        }

        if (Schema::hasTable('test_admins')) {
            DB::table('test_admins')->delete();
        }

        parent::tearDown();
    }

    public function test_it_shows_an_inline_error_and_keeps_the_visitor_when_the_erasure_fails(): void
    {
        $visitor = Visitor::create([
            'uuid' => (string) Str::uuid(),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'session_count' => 0,
        ]);

        $this->actingAs(TestAdmin::create(['email' => 'admin@example.test']), 'admin');

        // The events table is the first the action empties: the deletion fails
        // there, and the visitor must not go for all that.
        $this->withoutTable('falcon_analytics_events', function () use ($visitor): void {
            Livewire::test(VisitorDetailPage::class, ['visitor' => $visitor])
                ->call('forget')
                ->assertHasErrors('visitor-erasure-failed')
                ->assertNoRedirect();
        });

        $this->assertTrue(Visitor::whereKey($visitor->id)->exists());
    }
}
