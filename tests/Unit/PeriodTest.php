<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Unit;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The period every dashboard screen reads, and it comes from the address bar.
 *
 * `#[Url] public int $period` means a visitor types the value · the filter is
 * part of what a host can link to, which is the point, and it also means the
 * number reaching the date arithmetic is not one we chose.
 *
 * **So the clamp is the guarantee here, not the arithmetic.** Three lengths are
 * offered and anything else falls back, which keeps `?period=99999` from
 * turning a linked shortcut into a full-table scan — and keeps a mistyped link
 * showing the usual month rather than an error.
 *
 * Written on 2026-09-13, when `docs/fonctionnalites.md` started telling hosts
 * they may write these parameters themselves.
 */
final class PeriodTest extends TestCase
{
    /**
     * The three lengths the documentation tells hosts they may write.
     *
     * **Listed here rather than read from the constant**, and that is the whole
     * value of the list · comparing the constant to itself proves nothing, and
     * PHPStan says so. These are literals because they are a promise made
     * elsewhere, in `docs/fonctionnalites.md`. Change the constant and these
     * fall, which is the point.
     *
     * @return array<string, array{0: int}>
     */
    public static function allowedLengths(): array
    {
        return ['a week' => [7], 'a month' => [30], 'a quarter' => [90]];
    }

    #[DataProvider('allowedLengths')]
    public function test_it_keeps_a_length_it_offers(int $days): void
    {
        $this->assertSame($days, Period::ofDays($days)->days);
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function refusedLengths(): array
    {
        return [
            'a length nobody offers' => [14],
            'the one that would scan everything' => [99999],
            'zero' => [0],
            'a negative, which would invert the window' => [-7],
        ];
    }

    #[DataProvider('refusedLengths')]
    public function test_it_falls_back_on_anything_else_rather_than_refusing(int $days): void
    {
        $period = Period::ofDays($days);

        $this->assertSame(Period::DEFAULT_DAYS, $period->days);

        // And the window is a real one, forward in time: a negative length that
        // slipped through would put `from` after `to`, and every screen would
        // read an empty range without a word.
        $this->assertTrue($period->from->lessThan($period->to));
    }

    /**
     * The window covers the whole of its first day.
     *
     * `subDays($days - 1)->startOfDay()` and not `subDays($days)` · seven days
     * means today and the six before it, so a week reads as a week rather than
     * as eight days.
     */
    public function test_it_counts_today_as_one_of_the_days(): void
    {
        CarbonImmutable::setTestNow('2026-06-15 14:30:00');

        $period = Period::ofDays(7);

        $this->assertSame('2026-06-09 00:00:00', $period->from->toDateTimeString());
        $this->assertSame('2026-06-15 14:30:00', $period->to->toDateTimeString());

        CarbonImmutable::setTestNow();
    }

    /**
     * The previous window is made of whole days, and it touches this one.
     *
     * It ended at the same hour of the day as the current window at first —
     * 14:30, a week earlier — and two things were wrong with that. The hours
     * from 14:30 to midnight on that day belonged to neither window, so a visit
     * there counted nowhere. And a window ending mid-day cannot be read from
     * the daily summaries, which know whole days only · the « previous » figure
     * of the two summarised blocks moved on the day the erasing crossed it.
     */
    public function test_the_previous_window_is_whole_days_touching_this_one(): void
    {
        CarbonImmutable::setTestNow('2026-06-15 14:30:00');

        $previous = Period::ofDays(7)->previous();

        $this->assertSame('2026-06-02 00:00:00', $previous->from->toDateTimeString());
        $this->assertSame('2026-06-08 23:59:59', $previous->to->toDateTimeString(), 'It ends the second before this window begins.');
        $this->assertSame(7, $previous->days);
        $this->assertCount(7, $previous->eachDay());

        CarbonImmutable::setTestNow();
    }
}
