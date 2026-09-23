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
use Livewire\Livewire;

/**
 * Erasing a visitor fails, and the screen says so without losing anything.
 *
 * Breaking a table needs DDL, which implicitly commits the transaction the
 * bench holds open, and the action would then fail on a missing savepoint
 * instead of on its deletion. This class keeps `RefreshDatabase` for the
 * migration without its transactional wrapper, so `tearDown` tidies up.
 */
final class TheErasureKeepsWhatItCannotDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function beginDatabaseTransaction(): void
    {
        // No transaction: the DDL in the test would commit it.
    }

    protected function tearDown(): void
    {
        // Nothing rolls back on its own, so what this test wrote is deleted here.
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
        $visitor = Visitor::factory()->create();

        $this->actingAs(TestAdmin::create(['email' => 'admin@example.test']), 'admin');

        // The events table is the first the action empties, so the deletion fails there.
        $this->withoutTable('falcon_analytics_events', function () use ($visitor): void {
            Livewire::test(VisitorDetailPage::class, ['visitor' => $visitor])
                ->call('forget')
                ->assertHasErrors('visitor-erasure-failed')
                ->assertSee('La suppression a échoué.')
                // Drawn as an error: the kit's alert reads `type` and ignores any other name.
                ->assertSee('ui:bg-red-50', false)
                ->assertNoRedirect();
        });

        $this->assertTrue(Visitor::whereKey($visitor->id)->exists());
    }
}
