<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard;

use Carbon\CarbonImmutable;

/**
 * A closed date range for a dashboard query, expressed as a whole number of
 * days ending now. Only a fixed set of window sizes is allowed; any other value
 * falls back to the default so the range can never be driven out of bounds by a
 * tampered query string.
 */
final readonly class Period
{
    /** @var list<int> */
    public const ALLOWED_DAYS = [7, 30, 90];

    public const DEFAULT_DAYS = 30;

    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public int $days,
    ) {}

    public static function ofDays(int $days): self
    {
        if (! in_array($days, self::ALLOWED_DAYS, true)) {
            $days = self::DEFAULT_DAYS;
        }

        $to = CarbonImmutable::now();
        $from = $to->subDays($days - 1)->startOfDay();

        return new self($from, $to, $days);
    }
}
