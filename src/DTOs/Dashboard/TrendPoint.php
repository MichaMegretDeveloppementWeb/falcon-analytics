<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard;

use Carbon\CarbonImmutable;

/**
 * One day of the overview trend: the sessions and page views that started that
 * day. Missing days are filled with zeroes so the series is continuous.
 */
final readonly class TrendPoint
{
    public function __construct(
        public CarbonImmutable $date,
        public int $sessions,
        public int $pageviews,
    ) {}
}
