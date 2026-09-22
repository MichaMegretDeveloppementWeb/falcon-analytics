<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\DailyCount;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * The two columns that hold a closed list are guarded by the engine, and the
 * engine's list is the code's.
 *
 * The type of an event and the kind of a summary are strings, as a portable
 * schema wants · the enum cast and the validation at the door keep them right
 * as long as nothing else writes them. The named constraint is what still holds
 * when something does, and the second half of this file keeps its list from
 * drifting away from the one the code reads.
 */
final class TheEngineKeepsTheListsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_engine_refuses_an_event_type_that_is_never_stored(): void
    {
        $session = $this->makeSession();

        foreach (['heartbeat', 'unknown'] as $type) {
            try {
                $this->insertEvent($session, $type);
                $this->fail("The engine stored an event of type « {$type} ».");
            } catch (QueryException $refused) {
                $this->assertStringContainsString('fa_events_type_check', $refused->getMessage());
            }
        }
    }

    public function test_the_engine_takes_every_type_the_package_stores(): void
    {
        $session = $this->makeSession();

        foreach ($this->storedTypes() as $type) {
            $this->insertEvent($session, $type);
        }

        $this->assertSame(count($this->storedTypes()), DB::table('falcon_analytics_events')->count());
    }

    public function test_the_engine_refuses_a_summary_of_an_unknown_kind(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('fa_daily_counts_kind_check');

        DB::table('falcon_analytics_daily_counts')->insert([
            'day' => '2026-09-01',
            'kind' => 'visitor',
            'signature' => str_repeat('a', 64),
            'label' => '/',
            'total' => 1,
        ]);
    }

    /** A type added to the enum without deciding whether it is stored turns this red. */
    public function test_the_type_constraint_lists_exactly_the_types_that_are_stored(): void
    {
        $this->assertSame($this->storedTypes(), $this->listedBy('fa_events_type_check'));
    }

    /** And a kind of summary added in the code without the engine knowing it. */
    public function test_the_kind_constraint_lists_exactly_the_kinds_that_are_summarised(): void
    {
        $kinds = array_values(array_filter(
            (new ReflectionClass(DailyCount::class))->getConstants(),
            fn (mixed $value, string $name): bool => str_starts_with($name, 'KIND_'),
            ARRAY_FILTER_USE_BOTH,
        ));
        sort($kinds);

        $this->assertNotSame([], $kinds, 'No kind was read: the comparison would pass on nothing.');
        $this->assertSame($kinds, $this->listedBy('fa_daily_counts_kind_check'));
    }

    /**
     * The values of the event types that become rows, sorted.
     *
     * @return list<string>
     */
    private function storedTypes(): array
    {
        $types = array_values(array_map(
            fn (EventType $type): string => $type->value,
            array_filter(EventType::cases(), fn (EventType $type): bool => $type->isStored()),
        ));
        sort($types);

        return $types;
    }

    /**
     * The quoted values a check constraint allows, sorted · read off the engine,
     * whichever way it writes the clause back.
     *
     * @return list<string>
     */
    private function listedBy(string $constraint): array
    {
        $clause = DB::table('information_schema.CHECK_CONSTRAINTS')
            ->whereRaw('CONSTRAINT_SCHEMA = DATABASE()')
            ->where('CONSTRAINT_NAME', $constraint)
            ->value('CHECK_CLAUSE');

        $this->assertIsString($clause, "The constraint {$constraint} does not exist.");

        // MySQL hands the quotes back escaped, `_utf8mb4\'click\'`.
        preg_match_all("/'([^']+)'/", str_replace('\\', '', $clause), $values);
        $listed = $values[1];
        sort($listed);

        return $listed;
    }

    private function makeSession(): Session
    {
        $visitor = Visitor::create([
            'uuid' => (string) Str::uuid(),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        return Session::create([
            'visitor_id' => $visitor->id,
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);
    }

    private function insertEvent(Session $session, string $type): void
    {
        DB::table('falcon_analytics_events')->insert([
            'session_id' => $session->id,
            'visitor_id' => $session->visitor_id,
            'occurred_at' => now(),
            'type' => $type,
        ]);
    }
}
