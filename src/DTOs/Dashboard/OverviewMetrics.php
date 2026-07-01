<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard;

/**
 * Headline counters for the overview page, over a Period and optional subject
 * type. All figures exclude sessions flagged as bots.
 */
final readonly class OverviewMetrics
{
    public function __construct(
        public int $visitors,
        public int $sessions,
        public int $pageviews,
    ) {}

    public function pagesPerSession(): float
    {
        return $this->sessions > 0 ? round($this->pageviews / $this->sessions, 1) : 0.0;
    }
}
