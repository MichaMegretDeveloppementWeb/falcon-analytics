<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Actions\ArchiveClosedDaysAction;
use Falcon\Analytics\Analytics as AnalyticsManager;
use Falcon\Analytics\Facades\Analytics;
use Falcon\Analytics\Models\DailyCount;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * `Analytics::forgetSubject()` · every row held on one subject of the host goes,
 * whatever profile it sits on, and nobody else's does.
 */
final class AForgottenSubjectLeavesNothingBehindTest extends TestCase
{
    use RefreshDatabase;

    private const CABINET = ['type' => 'client', 'id' => 7];

    private const NEIGHBOUR = ['type' => 'client', 'id' => 8];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-07-10 12:00:00'));
    }

    /** The proof the host asked for: two profiles of one subject, all gone, another subject untouched. */
    public function test_every_row_of_the_subject_goes_and_the_others_stay(): void
    {
        $main = $this->profile(self::CABINET);
        $this->profile(self::CABINET, mergedInto: $main);
        $second = $this->profile(self::CABINET);
        $this->visit($main, self::CABINET, events: 2);
        $this->visit($main, null, events: 1);
        $this->visit($second, self::CABINET, events: 3);

        $neighbour = $this->profile(self::NEIGHBOUR);
        $this->visit($neighbour, self::NEIGHBOUR, events: 2);
        $anonymous = $this->profile(null);
        $this->visit($anonymous, null, events: 1);

        $erased = Analytics::forgetSubject('client', 7);

        $this->assertSame(3 + 3 + 6, $erased, 'Three profiles, three sessions and six events.');
        $this->assertEqualsCanonicalizing([$neighbour->id, $anonymous->id], Visitor::query()->pluck('id')->all());
        $this->assertSame(2, Session::query()->count());
        $this->assertSame(3, Event::query()->count());
        $this->assertSame(0, Event::query()->whereNotIn('visitor_id', [$neighbour->id, $anonymous->id])->count());
    }

    /** A row carrying the subject on another person's profile goes, and that person keeps the rest. */
    public function test_what_the_subject_left_on_someone_elses_profile_goes_too(): void
    {
        $shared = $this->profile(self::NEIGHBOUR);
        $this->visit($shared, self::NEIGHBOUR, events: 2);
        $theirs = $this->visit($shared, self::CABINET, events: 2);
        Event::factory()->for($theirs)->create(['subject_type' => null, 'subject_id' => null]);
        $anonymous = $this->visit($shared, null, events: 1);
        Event::factory()->for($anonymous)->create(['subject_type' => 'client', 'subject_id' => 7]);

        $erased = Analytics::forgetSubject('client', 7);

        $this->assertSame(1 + 3 + 1, $erased, 'Their session, its three events (one sent before they signed in), and their event in an anonymous session.');
        $this->assertSame($shared->id, Visitor::query()->sole()->id);
        $this->assertSame(2, $shared->refresh()->session_count, 'The profile counts the sessions it still holds.');
        $this->assertSame(3, Event::query()->count());
        $this->assertSame(0, Event::query()->where('subject_id', 7)->count());
        $this->assertSame(0, Session::query()->where('subject_id', 7)->count());
    }

    /** Erasing one person never erases another: what they left on the profile moves to their own. */
    public function test_another_subjects_session_moves_to_their_own_profile(): void
    {
        $main = $this->profile(self::CABINET);
        $this->visit($main, self::CABINET, events: 1);
        $theirs = $this->visit($main, self::NEIGHBOUR, events: 2);
        $neighbour = $this->profile(self::NEIGHBOUR, firstSeen: '2026-07-08 10:00:00');

        Analytics::forgetSubject('client', 7);

        $this->assertSame($neighbour->id, $theirs->refresh()->visitor_id);
        $this->assertSame(2, Event::query()->where('session_id', $theirs->id)->where('visitor_id', $neighbour->id)->count());
        $this->assertSame(1, $neighbour->refresh()->session_count);
        $this->assertSame([$neighbour->id], Visitor::query()->pluck('id')->all());
    }

    /** Someone with no profile of their own is given one, rather than erased with the other's. */
    public function test_another_subject_without_a_profile_is_given_one(): void
    {
        $main = $this->profile(self::CABINET);
        $theirs = $this->visit($main, self::NEIGHBOUR, events: 2, at: '2026-07-03 09:00:00');

        Analytics::forgetSubject('client', 7);

        $home = Visitor::query()->sole();

        $this->assertSame($home->id, $theirs->refresh()->visitor_id);
        $this->assertSame(['client', 8], [$home->subject_type, $home->subject_id]);
        $this->assertNull($home->merged_into_id);
        $this->assertNotSame($main->uuid, $home->uuid, 'The browser of the person forgotten is not handed over.');
        $this->assertSame('2026-07-03 09:00:00', $home->first_seen_at->toDateTimeString());
        $this->assertSame(1, $home->session_count);
        $this->assertSame(2, Event::query()->where('visitor_id', $home->id)->count());
    }

    public function test_the_daily_totals_stay(): void
    {
        $main = $this->profile(self::CABINET);
        $session = $this->visit($main, self::CABINET, events: 0, at: '2026-07-05 10:00:00');
        Event::factory()->for($session)->count(3)->create([
            'occurred_at' => '2026-07-05 10:00:00',
            'url' => 'https://cabinet.test/rendez-vous',
            'page' => '/rendez-vous',
            'subject_type' => 'client',
            'subject_id' => 7,
        ]);
        $this->app->make(ArchiveClosedDaysAction::class)->execute();
        $totals = DailyCount::query()->orderBy('id')->get()->toArray();

        Analytics::forgetSubject('client', 7);

        $this->assertNotSame([], $totals);
        $this->assertSame($totals, DailyCount::query()->orderBy('id')->get()->toArray());
        $this->assertSame(0, Event::query()->count());
    }

    public function test_a_number_written_as_text_names_the_subject(): void
    {
        $this->visit($this->profile(self::CABINET), self::CABINET, events: 1);

        $this->assertSame(3, Analytics::forgetSubject('client', '7'));
        $this->assertSame(0, Visitor::query()->count());
    }

    public function test_a_subject_with_nothing_held_answers_zero(): void
    {
        $this->visit($this->profile(self::NEIGHBOUR), self::NEIGHBOUR, events: 1);

        $this->assertSame(0, Analytics::forgetSubject('client', 7));
        $this->assertSame(1, Visitor::query()->count());
    }

    #[DataProvider('idsNoSubjectCarries')]
    public function test_an_id_no_subject_carries_is_refused_and_nothing_goes(string $type, string $id): void
    {
        $this->visit($this->profile(self::CABINET), self::CABINET, events: 1);

        try {
            $this->app->make(AnalyticsManager::class)->forgetSubject($type, $id);
            $this->fail('The id was meant to be refused.');
        } catch (InvalidArgumentException $refusal) {
            $this->assertStringContainsString('nothing was erased', $refusal->getMessage());
        }

        $this->assertSame(1, Visitor::query()->count());
        $this->assertSame(1, Event::query()->count());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function idsNoSubjectCarries(): array
    {
        return [
            'a key with a numeric prefix' => ['client', '7abc'],
            'a decimal' => ['client', '7.0'],
            'a uuid' => ['client', '9f1c-4b2a-8e77'],
            'an empty key' => ['client', ''],
            'an empty type' => ['', '7'],
        ];
    }

    /** The lock is the first read of the transaction: what follows sees what a send wrote while it waited. */
    public function test_the_lock_is_the_first_read(): void
    {
        $main = $this->profile(self::CABINET);
        $this->profile(self::CABINET, mergedInto: $main);
        $this->visit($main, self::CABINET, events: 1);

        $queries = $this->queriesOnceATransactionOpens(fn () => Analytics::forgetSubject('client', 7));

        $this->assertStringContainsString('falcon_analytics_visitors', $queries[0] ?? '');
        $this->assertStringContainsString('for update', $queries[0] ?? '');
    }

    /** A profile a send made while the erasure read the others is read and locked in turn, and goes too. */
    public function test_a_profile_made_meanwhile_goes_too(): void
    {
        $this->visit($this->profile(self::CABINET), self::CABINET, events: 1);
        $made = false;

        DB::connection()->beforeExecuting(function (string $query) use (&$made): void {
            if (! $made && str_contains($query, 'for update')) {
                $made = true;
                $this->profile(self::CABINET);
            }
        });

        Analytics::forgetSubject('client', 7);

        $this->assertTrue($made);
        $this->assertSame(0, Visitor::query()->count());
    }

    /** Profiles that never hold still are not half erased. */
    public function test_profiles_that_keep_changing_are_left_whole(): void
    {
        $this->visit($this->profile(self::CABINET), self::CABINET, events: 1);

        DB::connection()->beforeExecuting(function (string $query): void {
            if (str_contains($query, 'for update')) {
                DB::table('falcon_analytics_visitors')->insert([
                    'uuid' => (string) Str::uuid(),
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                    'session_count' => 0,
                    'subject_type' => 'client',
                    'subject_id' => 7,
                ]);
            }
        });

        try {
            Analytics::forgetSubject('client', 7);
            $this->fail('The erasure was meant to give up.');
        } catch (RuntimeException $failure) {
            $this->assertStringContainsString('nothing was erased', $failure->getMessage());
        }

        $this->assertSame(1, Session::query()->count());
        $this->assertSame(1, Event::query()->count());
    }

    public function test_the_erasure_is_written_down(): void
    {
        $this->visit($this->profile(self::CABINET), self::CABINET, events: 1);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('notice')->once()->with('Subject.erased', ['subject_type' => 'client', 'subject_id' => 7, 'rows' => 3]);

        Analytics::forgetSubject('client', '7');
    }

    /**
     * @param  array{type: string, id: int}|null  $subject
     */
    private function profile(?array $subject, ?Visitor $mergedInto = null, string $firstSeen = '2026-07-01 10:00:00'): Visitor
    {
        return Visitor::factory()->create([
            'first_seen_at' => CarbonImmutable::parse($firstSeen),
            'last_seen_at' => CarbonImmutable::parse($firstSeen),
            'subject_type' => $subject['type'] ?? null,
            'subject_id' => $subject['id'] ?? null,
            'merged_into_id' => $mergedInto?->id,
        ]);
    }

    /**
     * A session on the profile, its events carrying the subject it carries.
     *
     * @param  array{type: string, id: int}|null  $subject
     */
    private function visit(Visitor $on, ?array $subject, int $events, string $at = '2026-07-05 10:00:00'): Session
    {
        $session = Session::factory()->for($on)->at(CarbonImmutable::parse($at))->create([
            'subject_type' => $subject['type'] ?? null,
            'subject_id' => $subject['id'] ?? null,
        ]);

        Event::factory()->for($session)->count($events)->create([
            'occurred_at' => CarbonImmutable::parse($at),
            'subject_type' => $subject['type'] ?? null,
            'subject_id' => $subject['id'] ?? null,
        ]);

        $on->increment('session_count');

        return $session;
    }

    /**
     * Every statement run once the gesture opens its transaction, in order.
     *
     * @return list<string>
     */
    private function queriesOnceATransactionOpens(callable $gesture): array
    {
        $open = false;
        $queries = [];

        $this->app->make('events')->listen(TransactionBeginning::class, function () use (&$open): void {
            $open = true;
        });

        DB::listen(function (QueryExecuted $query) use (&$open, &$queries): void {
            if ($open) {
                $queries[] = $query->sql;
            }
        });

        $gesture();

        return $queries;
    }
}
