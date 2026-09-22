<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

/**
 * A duration as every screen writes it · minutes and seconds, rounded to the
 * second, with non-breaking spaces so a figure never parts from its unit.
 *
 * @internal
 */
final class DurationLabel
{
    public static function for(int|float $seconds): string
    {
        $total = (int) round($seconds);
        $minutes = intdiv($total, 60);
        $rest = $total % 60;

        if ($minutes > 0) {
            return $rest > 0 ? "{$minutes}\u{00A0}min\u{00A0}{$rest}\u{00A0}s" : "{$minutes}\u{00A0}min";
        }

        return "{$total}\u{00A0}s";
    }
}
