<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Services\DailyCountArchiver;
use Falcon\Analytics\Services\VisitorMerger;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

/**
 * A service whose writes stand or fall together refuses to run on its own.
 *
 * The transaction belongs to the action that calls it. Called without one, the
 * service would write the first half and leave the second to chance · it says
 * so instead, before a single statement is sent.
 *
 * **A file of its own, and for a mechanical reason** · the bench wraps every
 * test in a transaction, so a call made there always has one. This class keeps
 * `RefreshDatabase` for the migration and neutralises that wrapper, and
 * `tearDown` removes whatever a service that did not refuse would have written.
 */
final class WhatMustHoldTogetherAsksForATransactionTest extends TestCase
{
    use RefreshDatabase;

    public function beginDatabaseTransaction(): void
    {
        // No transaction: the services under test must meet none.
    }

    protected function tearDown(): void
    {
        foreach (['falcon_analytics_daily_counts', 'falcon_analytics_daily_archives'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        parent::tearDown();
    }

    public function test_a_merge_refuses_to_run_outside_a_transaction(): void
    {
        $this->expectException(LogicException::class);

        app(VisitorMerger::class)->execute(new Visitor, new Visitor);
    }

    public function test_a_relocation_refuses_to_run_outside_a_transaction(): void
    {
        $this->expectException(LogicException::class);

        app(VisitorMerger::class)->relocateForeignSessions('client', 7, new Visitor);
    }

    public function test_a_day_summary_refuses_to_run_outside_a_transaction(): void
    {
        $this->expectException(LogicException::class);

        app(DailyCountArchiver::class)->archive(CarbonImmutable::parse('2026-06-12'));
    }
}
