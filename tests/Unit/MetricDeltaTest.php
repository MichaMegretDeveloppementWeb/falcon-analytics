<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Unit;

use Falcon\Analytics\DTOs\Dashboard\MetricDelta;
use PHPUnit\Framework\TestCase;

final class MetricDeltaTest extends TestCase
{
    public function test_it_has_a_baseline_only_when_the_previous_value_is_non_zero(): void
    {
        $this->assertTrue((new MetricDelta(10, 5))->hasBaseline());
        $this->assertFalse((new MetricDelta(10, 0))->hasBaseline());
    }

    public function test_it_computes_the_period_over_period_percentage_change_rounded_to_one_decimal(): void
    {
        $this->assertSame(50.0, (new MetricDelta(150, 100))->changePercent());
        $this->assertSame(-25.0, (new MetricDelta(75, 100))->changePercent());
        $this->assertSame(-66.7, (new MetricDelta(1, 3))->changePercent());
    }

    public function test_it_returns_a_zero_change_when_there_is_no_baseline_never_dividing_by_zero(): void
    {
        $this->assertSame(0.0, (new MetricDelta(42, 0))->changePercent());
    }

    public function test_it_reports_whether_the_metric_increased(): void
    {
        $this->assertTrue((new MetricDelta(10, 5))->hasIncreased());
        $this->assertFalse((new MetricDelta(5, 10))->hasIncreased());
        $this->assertFalse((new MetricDelta(5, 5))->hasIncreased());
    }
}
