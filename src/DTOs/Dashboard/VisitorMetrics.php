<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard;

/**
 * The four headline tiles of the visitors screen, each carrying its delta and
 * sparkline series. Assembled by the VisitorMetricsCalculator.
 */
final readonly class VisitorMetrics
{
    public function __construct(
        public MetricTrend $visitors,
        public MetricTrend $newVisitors,
        public MetricTrend $returning,
        public MetricTrend $sessionsPerVisitor,
    ) {}
}
