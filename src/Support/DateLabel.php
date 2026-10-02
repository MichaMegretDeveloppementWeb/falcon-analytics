<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Every date the package shows, written in the package's language · Carbon's
 * own locale is the host's, so it is never relied on.
 *
 * @internal
 */
final class DateLabel
{
    /** The date in the given PHP format, its names in French · `j M Y` reads « 7 sept. 2026 ». */
    public static function for(DateTimeInterface $date, string $format): string
    {
        return CarbonImmutable::instance($date)->settings(['locale' => Language::CODE])->translatedFormat($format);
    }
}
