<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard;

/**
 * A metric together with its value over the previous period, for
 * period-over-period comparison. The percentage change is only meaningful when
 * a baseline exists (previous > 0).
 */
final readonly class MetricDelta
{
    public function __construct(
        public float $current,
        public float $previous,
    ) {}

    public function hasBaseline(): bool
    {
        return $this->previous != 0.0;
    }

    public function changePercent(): float
    {
        if ($this->previous == 0.0) {
            return 0.0;
        }

        return round(($this->current - $this->previous) / $this->previous * 100, 1);
    }

    public function increased(): bool
    {
        return $this->current > $this->previous;
    }
}
