<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The bench runs Eloquent strict, as a host does outside production · a list
 * that reads a relation it never loaded, or a column it never selected, fails
 * here instead of costing a query per row in production, where both guards are
 * off and nothing says so.
 */
final class TheBenchIsStrictTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_relation_read_in_passing_is_refused(): void
    {
        $this->twoSessions();
        $sessions = Session::query()->orderBy('id')->get();

        $this->expectException(LazyLoadingViolationException::class);

        $sessions->map(fn (Session $session): Visitor => $session->visitor);
    }

    public function test_a_column_never_read_is_refused(): void
    {
        $this->twoSessions();

        $this->expectException(MissingAttributeException::class);

        Session::query()->select(['id'])->firstOrFail()->getAttribute('source');
    }

    /** Two, because the lazy-loading guard only speaks once more than one record was hydrated. */
    private function twoSessions(): void
    {
        foreach ([1, 2] as $rank) {
            Session::factory()
                ->for(Visitor::factory()->state(['session_count' => 1]))
                ->create(['pageview_count' => $rank, 'source' => 'direct']);
        }
    }
}
