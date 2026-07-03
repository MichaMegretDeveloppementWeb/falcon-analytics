<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard;

/**
 * A KPI tile: its period-over-period delta plus a daily series for the
 * sparkline. Pure read-model, no behaviour.
 */
final readonly class MetricTrend
{
    /**
     * @param  list<float>  $sparkline
     */
    public function __construct(
        public MetricDelta $delta,
        public array $sparkline,
    ) {}
}
