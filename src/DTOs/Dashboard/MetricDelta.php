<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard;

/**
 * A metric together with its value over the previous period, for
 * period-over-period comparison. The percentage change is only meaningful when
 * a baseline exists (previous > 0).
 *
 * A figure that counts distinct people has no value over a period reaching
 * days whose sessions are erased · null then, for the period or its previous
 * one, and the screen says it is unavailable.
 *
 * @internal
 */
final readonly class MetricDelta
{
    public function __construct(
        public ?float $current,
        public ?float $previous,
    ) {}

    /** Whether the period's own value is known. */
    public function isAvailable(): bool
    {
        return $this->current !== null;
    }

    /** Whether both values are known, so the two can be compared. */
    public function isComparable(): bool
    {
        return $this->current !== null && $this->previous !== null;
    }

    public function hasBaseline(): bool
    {
        return $this->previous !== null && $this->previous !== 0.0;
    }

    public function changePercent(): float
    {
        if ($this->current === null || $this->previous === null || $this->previous === 0.0) {
            return 0.0;
        }

        return round(($this->current - $this->previous) / $this->previous * 100, 1);
    }

    public function hasIncreased(): bool
    {
        return $this->isComparable() && $this->current > $this->previous;
    }
}
