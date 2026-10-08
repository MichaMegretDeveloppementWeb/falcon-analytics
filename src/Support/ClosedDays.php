<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

use Carbon\CarbonImmutable;

/**
 * The days a summary may take · from a given day up to the last one that is
 * really closed, shared by every family of summaries.
 *
 * @internal
 */
final class ClosedDays
{
    /**
     * How long after midnight a day is still considered open.
     *
     * **A row can land after the day it belongs to has ended.** The collector
     * stamps an event with the moment it happened and sends it a few seconds
     * later; the endpoint writes it after the response has gone. So a visit at
     * 23:59:58 reaches the table at 00:00:05 — and a summary written in
     * between would never count it, while the erasing would still take it. The
     * scheduler runs at 03:00 and is never caught by this; the catch-up on a
     * screen load runs whenever an administrator opens one, midnight included.
     *
     * An hour is far beyond any delay the collector can produce, and it costs
     * nothing · the nightly run comes later anyway.
     */
    private const GRACE_MINUTES = 60;

    /**
     * The days from `$from` up to yesterday, once the grace after midnight has
     * passed, oldest first.
     *
     * @return list<CarbonImmutable>
     */
    public static function since(CarbonImmutable $from, ?int $limit = null): array
    {
        $yesterday = CarbonImmutable::now()->subMinutes(self::GRACE_MINUTES)->subDay()->startOfDay();
        $days = [];

        for ($day = $from->startOfDay(); $day->lessThanOrEqualTo($yesterday); $day = $day->addDay()) {
            $days[] = $day;

            if ($limit !== null && count($days) >= $limit) {
                break;
            }
        }

        return $days;
    }
}
