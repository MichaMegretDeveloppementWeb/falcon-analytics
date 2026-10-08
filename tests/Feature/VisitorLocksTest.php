<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Services\VisitorLocks;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;

/** The one lock every writer of a visitor profile takes. */
final class VisitorLocksTest extends TestCase
{
    use RefreshDatabase;

    /** In ascending order and one statement per row, so two writers never wait on each other in a circle. */
    public function test_profiles_are_locked_one_by_one_in_ascending_order(): void
    {
        $ids = Visitor::factory()->count(3)->create()->pluck('id')->all();
        $locked = [];

        DB::listen(function (QueryExecuted $query) use (&$locked): void {
            if (str_contains($query->sql, 'for update')) {
                $locked[] = $query->bindings;
            }
        });

        DB::transaction(fn () => $this->app->make(VisitorLocks::class)->lock([$ids[2], $ids[0], $ids[1], $ids[0]]));

        $this->assertSame([[$ids[0]], [$ids[1]], [$ids[2]]], $locked);
    }

    public function test_a_profile_gone_is_left_out(): void
    {
        $kept = Visitor::factory()->create();
        $gone = Visitor::factory()->create();
        $gone->delete();

        $locked = DB::transaction(fn (): array => $this->app->make(VisitorLocks::class)->lock([$gone->id, $kept->id]));

        $this->assertSame([$kept->id], array_keys($locked));
        $this->assertNull(DB::transaction(fn () => $this->app->make(VisitorLocks::class)->one($gone->id)));
    }

    /** Outside a transaction the lock would be released at once · the bench's own is set aside for the call. */
    public function test_outside_a_transaction_the_lock_is_refused(): void
    {
        DB::rollBack(0);

        try {
            $this->app->make(VisitorLocks::class)->one(1);
            $this->fail('The lock was meant to be refused.');
        } catch (LogicException $refusal) {
            $this->assertStringContainsString('inside the caller\'s transaction', $refusal->getMessage());
        } finally {
            DB::beginTransaction();
        }
    }
}
