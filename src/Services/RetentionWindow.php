<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Models\DailyArchive;

/**
 * Where a reading of sessions or named events splits · up to the line, the
 * days come from their daily totals, their rows being partly erased · past it,
 * from the rows, all there.
 *
 * The line is what the purge recorded before erasing, never deduced from the
 * settings · a retention changed after an erasing would otherwise read rows
 * that are gone. No line while nothing was erased, and the rows alone are read.
 *
 * A figure that counts distinct people cannot be split this way · a period
 * that reaches the line has none.
 *
 * @internal
 */
final class RetentionWindow
{
    /** The last day whose sessions are read from the totals, or null. */
    public function sessionsLine(): ?CarbonImmutable
    {
        return DailyArchive::lastSessionsPrunedDay();
    }

    /** The same for named events · a session erased takes its events with it. */
    public function eventsLine(): ?CarbonImmutable
    {
        $sessions = $this->sessionsLine();
        $events = DailyArchive::lastEventsPrunedDay();

        if ($sessions === null || $events === null) {
            return $sessions ?? $events;
        }

        return $sessions->max($events);
    }

    /** Whether a period reaches back to the line, so its rows are not all there. */
    public static function reaches(Period $period, ?CarbonImmutable $line): bool
    {
        return $line !== null && $period->from->lessThanOrEqualTo($line->endOfDay());
    }

    /**
     * The part of a period read from the totals, then the part read from the
     * rows · either can be null, never both.
     *
     * @return array{totals: Period|null, rows: Period|null}
     */
    public static function split(Period $period, ?CarbonImmutable $line): array
    {
        if ($line === null || ! self::reaches($period, $line)) {
            return ['totals' => null, 'rows' => $period];
        }

        $boundary = $line->endOfDay();

        if ($boundary->greaterThanOrEqualTo($period->to)) {
            return ['totals' => $period, 'rows' => null];
        }

        return [
            'totals' => new Period($period->from, $boundary, $period->days),
            'rows' => new Period($boundary->addSecond(), $period->to, $period->days),
        ];
    }
}
