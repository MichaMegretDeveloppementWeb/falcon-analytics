<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Models\DailyArchive;
use Falcon\Analytics\Services\RetentionWindow;
use Falcon\Analytics\Support\RetentionSettings;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The three retention settings, judged together · and the line a reading
 * splits on, which is what the purge recorded, never what the settings say.
 */
final class TheRetentionLineIsWhatThePurgeRecordedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
    }

    public function test_out_of_the_box_only_the_anonymous_rows_are_erased(): void
    {
        $this->assertSame(90, RetentionSettings::pages());
        $this->assertNull(RetentionSettings::sessions());
        $this->assertNull(RetentionSettings::events());
        $this->assertNull(RetentionSettings::refusal());
    }

    /** A named event leaves with its session · it is never kept longer. */
    public function test_named_events_never_outlive_their_session(): void
    {
        config(['analytics.session_retention_days' => 400, 'analytics.event_retention_days' => 200]);
        $this->assertSame(200, RetentionSettings::events());

        config(['analytics.event_retention_days' => null]);
        $this->assertSame(400, RetentionSettings::events(), 'Sessions erased take their events.');

        config(['analytics.session_retention_days' => null, 'analytics.event_retention_days' => 200]);
        $this->assertSame(200, RetentionSettings::events());
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    #[DataProvider('settingsThatContradict')]
    public function test_settings_that_cannot_be_acted_on_are_refused(array $settings, string $named): void
    {
        config(collect($settings)->mapWithKeys(fn (mixed $value, string $key): array => ["analytics.{$key}" => $value])->all());

        $this->assertStringContainsString($named, (string) RetentionSettings::refusal());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function settingsThatContradict(): array
    {
        return [
            'sessions for zero days' => [['session_retention_days' => 0], 'analytics.session_retention_days doit être'],
            'sessions as text' => [['session_retention_days' => '400'], 'analytics.session_retention_days doit être'],
            'events for a negative number' => [['event_retention_days' => -3], 'analytics.event_retention_days doit être'],
            'sessions shorter than the page views' => [['retention_days' => 90, 'session_retention_days' => 60], 'est plus court que analytics.retention_days (90 jours)'],
            'sessions erased, page views never' => [['retention_days' => null, 'session_retention_days' => 400], 'jamais effacé'],
            'events longer than their sessions' => [['session_retention_days' => 90, 'event_retention_days' => 120], 'est plus long que analytics.session_retention_days'],
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    #[DataProvider('settingsThatHold')]
    public function test_settings_that_hold_together_are_accepted(array $settings): void
    {
        config(collect($settings)->mapWithKeys(fn (mixed $value, string $key): array => ["analytics.{$key}" => $value])->all());

        $this->assertNull(RetentionSettings::refusal());
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function settingsThatHold(): array
    {
        return [
            'as many days for each' => [['retention_days' => 90, 'session_retention_days' => 90, 'event_retention_days' => 90]],
            'twenty-five months of sessions' => [['retention_days' => 90, 'session_retention_days' => 760, 'event_retention_days' => 760]],
            'events alone' => [['event_retention_days' => 30]],
            'nothing erased at all' => [['retention_days' => null]],
        ];
    }

    /** Nothing erased, no line · the rows alone are read, whatever the settings say. */
    public function test_without_an_erasing_there_is_no_line(): void
    {
        config(['analytics.session_retention_days' => 90, 'analytics.event_retention_days' => 30]);
        $window = $this->app->make(RetentionWindow::class);

        $this->assertNull($window->sessionsLine());
        $this->assertNull($window->eventsLine());
        $period = Period::ofDays(90);
        $this->assertSame(['totals' => null, 'rows' => $period], RetentionWindow::split($period, $window->sessionsLine()));
    }

    /** The line is the last day the purge marked · a session erased takes its named events too. */
    public function test_the_line_is_the_last_day_the_purge_marked(): void
    {
        $this->mark('2026-03-01', sessions: true, events: true);
        $this->mark('2026-03-02', sessions: true, events: true);
        $this->mark('2026-04-10', sessions: false, events: true);
        $this->mark('2026-05-01', sessions: false, events: false);
        $window = $this->app->make(RetentionWindow::class);

        $this->assertSame('2026-03-02', $window->sessionsLine()?->toDateString());
        $this->assertSame('2026-04-10', $window->eventsLine()?->toDateString());

        $this->mark('2026-04-20', sessions: true, events: false);
        $line = (new RetentionWindow)->eventsLine();

        $this->assertSame('2026-04-20', $line?->toDateString(), 'Its sessions gone, a day has no event left either.');
    }

    public function test_a_period_is_split_at_the_end_of_the_line(): void
    {
        $line = CarbonImmutable::parse('2026-05-31');
        $across = new Period(CarbonImmutable::parse('2026-05-17')->startOfDay(), CarbonImmutable::now(), 30);

        ['totals' => $totals, 'rows' => $rows] = RetentionWindow::split($across, $line);

        $this->assertNotNull($totals);
        $this->assertNotNull($rows);
        $this->assertSame('2026-05-17 00:00:00', $totals->from->toDateTimeString());
        $this->assertSame('2026-05-31 23:59:59', $totals->to->toDateTimeString());
        $this->assertSame('2026-06-01 00:00:00', $rows->from->toDateTimeString());
        $this->assertSame('2026-06-15 12:00:00', $rows->to->toDateTimeString());
        $this->assertTrue(RetentionWindow::reaches($across, $line));

        $before = new Period(CarbonImmutable::parse('2026-05-01')->startOfDay(), CarbonImmutable::parse('2026-05-31')->endOfDay(), 31);
        $this->assertSame(['totals' => $before, 'rows' => null], RetentionWindow::split($before, $line));

        $after = new Period(CarbonImmutable::parse('2026-06-01')->startOfDay(), CarbonImmutable::now(), 15);
        $this->assertSame(['totals' => null, 'rows' => $after], RetentionWindow::split($after, $line));
        $this->assertFalse(RetentionWindow::reaches($after, $line));
    }

    private function mark(string $day, bool $sessions, bool $events): void
    {
        DailyArchive::query()->create([
            'day' => $day,
            'archived_at' => '2026-06-15 03:00:00',
            'detail_archived_at' => '2026-06-15 03:00:00',
            'sessions_pruned_at' => $sessions ? '2026-06-15 03:30:00' : null,
            'events_pruned_at' => $events ? '2026-06-15 03:30:00' : null,
        ]);
    }
}
