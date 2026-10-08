<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Actions\PruneProfilesLeftEmptyAction;
use Falcon\Analytics\Models\DailyArchive;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The retention of sessions, profiles and named events · for each sort of row,
 * an old one erased, a recent one kept, and a day not yet summarised kept.
 */
final class TheRetentionErasesWhatItSaysTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
        config([
            'analytics.retention_days' => 30,
            'analytics.session_retention_days' => 60,
            'analytics.event_retention_days' => 45,
        ]);
    }

    /** The proof the host asked for · sessions past sixty days go, the recent ones stay. */
    public function test_an_old_session_goes_and_a_recent_one_stays(): void
    {
        $old = $this->sessionOn(Visitor::factory()->create(), '2026-04-01 10:00:00');
        $recent = $this->sessionOn(Visitor::factory()->create(), '2026-05-20 10:00:00');

        $this->archiveThenPrune();

        $this->assertNull(Session::query()->find($old->id));
        $this->assertNotNull(Session::query()->find($recent->id));
        $this->assertSame(0, Event::query()->where('session_id', $old->id)->count(), 'Its events went with it.');
    }

    /** A profile its sessions left empty goes, with its alias · one with a session left stays, counted again. */
    public function test_a_profile_left_empty_goes_with_its_alias_and_another_is_counted_again(): void
    {
        $gone = $this->profile('2026-04-01 10:00:00');
        $alias = $this->profile('2026-03-20 10:00:00', mergedInto: $gone);
        $this->sessionOn($gone, '2026-04-01 10:00:00');

        $seenLately = $this->profile('2026-06-01 10:00:00');

        $back = $this->profile('2026-05-20 10:00:00');
        $this->sessionOn($back, '2026-04-01 10:00:00');
        $this->sessionOn($back, '2026-05-20 10:00:00');
        $back->update(['session_count' => 2]);

        $this->archiveThenPrune();

        $this->assertNull(Visitor::query()->find($gone->id));
        $this->assertNull(Visitor::query()->find($alias->id), 'An alias never stays on its own.');
        $this->assertNotNull(Visitor::query()->find($seenLately->id), 'Seen within the retention, a profile stays.');
        $this->assertSame(1, $back->refresh()->session_count);
        $this->assertSame('2026-05-20 10:00:00', $back->first_seen_at->toDateTimeString(), 'The first visit stays known, and keeps « new » exact.');
    }

    /** Named events past forty-five days go, in a session kept · the recent ones and the page views stay. */
    public function test_an_old_named_event_goes_and_a_recent_one_stays(): void
    {
        $session = $this->sessionOn(Visitor::factory()->create(), '2026-04-25 10:00:00');
        $old = Event::factory()->for($session)->custom('devis.demande')->create(['occurred_at' => '2026-04-25 10:00:00']);
        $view = Event::factory()->for($session)->create(['occurred_at' => '2026-04-25 10:01:00', 'route' => 'tarifs']);
        $recent = Event::factory()->for($this->sessionOn(Visitor::factory()->create(), '2026-05-20 10:00:00'))->custom('devis.demande')->create(['occurred_at' => '2026-05-20 10:00:00']);

        $this->archiveThenPrune();

        $this->assertNull(Event::query()->find($old->id));
        $this->assertNotNull(Event::query()->find($recent->id));
        $this->assertNotNull(Session::query()->find($session->id), 'The session itself is kept sixty days.');
        $this->assertNull(Event::query()->find($view->id), 'Past thirty days, an anonymous page view goes as before.');
    }

    /** A day not yet summarised keeps all its rows · no summary, no erasing. */
    public function test_a_day_not_yet_summarised_keeps_its_rows(): void
    {
        $session = $this->sessionOn(Visitor::factory()->create(), '2026-04-01 10:00:00');
        Event::factory()->for($session)->custom('devis.demande')->create(['occurred_at' => '2026-04-01 10:00:00']);

        $this->artisan('analytics:archive', ['--days' => '3'])->assertSuccessful();
        $this->artisan('analytics:prune')->assertSuccessful();

        $this->assertNotNull(Session::query()->find($session->id));
        $this->assertSame(1, Event::query()->where('name', 'devis.demande')->count());
        $this->assertSame(0, DailyArchive::query()->whereNotNull('sessions_pruned_at')->count());
    }

    /** The register says which days were erased, and says it before the rows go. */
    public function test_the_days_erased_are_written_down(): void
    {
        $this->sessionOn(Visitor::factory()->create(), '2026-04-01 10:00:00');
        $kept = $this->sessionOn(Visitor::factory()->create(), '2026-04-20 10:00:00');
        Event::factory()->for($kept)->custom('devis.demande')->create(['occurred_at' => '2026-04-20 10:00:00']);
        $marked = null;

        $this->artisan('analytics:archive')->assertSuccessful();

        DB::listen(function ($query) use (&$marked): void {
            if ($marked === null && str_starts_with($query->sql, 'delete from `falcon_analytics_sessions`')) {
                $marked = DailyArchive::query()->whereNotNull('sessions_pruned_at')->max('day');
            }
        });

        $this->artisan('analytics:prune')->assertSuccessful();

        $this->assertSame('2026-04-15', $marked, 'The days before the cutoff were marked before the first session went.');
        $this->assertSame('2026-04-15', DailyArchive::lastPrunedDays()['sessions']?->toDateString());
        $this->assertSame('2026-04-30', DailyArchive::lastPrunedDays()['events']?->toDateString());
    }

    /** A visitor coming back while the purge reads their profile keeps it · the lock reads it again. */
    public function test_a_visitor_coming_back_during_the_purge_keeps_the_profile(): void
    {
        $profile = $this->profile('2026-04-01 10:00:00');
        $came = false;

        DB::connection()->beforeExecuting(function (string $query) use ($profile, &$came): void {
            if (! $came && str_contains($query, 'for update') && str_contains($query, 'falcon_analytics_visitors')) {
                $came = true;
                Session::factory()->for($profile)->at(CarbonImmutable::now())->create();
            }
        });

        $erased = $this->app->make(PruneProfilesLeftEmptyAction::class)->execute();

        $this->assertTrue($came);
        $this->assertSame(0, $erased);
        $this->assertNotNull(Visitor::query()->find($profile->id));
    }

    /**
     * @param  array<string, int|null>  $settings
     */
    #[DataProvider('durationsThatContradict')]
    public function test_durations_that_contradict_erase_nothing_and_say_why(array $settings, string $said): void
    {
        config(collect($settings)->mapWithKeys(fn (?int $value, string $key): array => ["analytics.{$key}" => $value])->all());
        $session = $this->sessionOn(Visitor::factory()->create(), '2026-01-10 10:00:00');
        $empty = $this->profile('2026-01-10 10:00:00');
        $this->artisan('analytics:archive')->assertSuccessful();

        $this->artisan('analytics:prune')->assertFailed()->expectsOutputToContain($said);

        $this->assertNotNull(Session::query()->find($session->id));
        $this->assertNotNull(Visitor::query()->find($empty->id));
        $this->assertSame(0, $this->app->make(PruneProfilesLeftEmptyAction::class)->execute());
    }

    /**
     * @return array<string, array{array<string, int|null>, string}>
     */
    public static function durationsThatContradict(): array
    {
        return [
            'sessions shorter than the page views' => [['retention_days' => 90, 'session_retention_days' => 60], 'est plus court que analytics.retention_days'],
            'named events longer than their sessions' => [['session_retention_days' => 60, 'event_retention_days' => 90], 'est plus long que analytics.session_retention_days'],
        ];
    }

    private function archiveThenPrune(): void
    {
        $this->artisan('analytics:archive')->assertSuccessful();
        $this->artisan('analytics:prune')->assertSuccessful();
    }

    private function profile(string $seen, ?Visitor $mergedInto = null): Visitor
    {
        return Visitor::factory()->create([
            'first_seen_at' => CarbonImmutable::parse($seen),
            'last_seen_at' => CarbonImmutable::parse($seen),
            'merged_into_id' => $mergedInto?->id,
        ]);
    }

    private function sessionOn(Visitor $visitor, string $at): Session
    {
        $session = Session::factory()->for($visitor)->at(CarbonImmutable::parse($at))->create(['pageview_count' => 1]);
        Event::factory()->for($session)->create(['occurred_at' => $at, 'url' => 'https://site.test/', 'page' => '/', 'route' => 'home']);

        return $session;
    }
}
