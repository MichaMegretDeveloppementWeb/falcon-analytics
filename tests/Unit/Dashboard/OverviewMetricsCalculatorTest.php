<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Unit\Dashboard;

use Falcon\Analytics\Services\Dashboard\OverviewMetricsCalculator;
use PHPUnit\Framework\TestCase;

final class OverviewMetricsCalculatorTest extends TestCase
{
    public function test_it_computes_the_new_visitor_rate_period_over_period(): void
    {
        $rate = (new OverviewMetricsCalculator)->newVisitorRate(
            ['new' => 3, 'returning' => 1], // 75 %
            ['new' => 1, 'returning' => 1], // 50 %
        );

        $this->assertSame(75.0, $rate->current);
        $this->assertSame(50.0, $rate->previous);
    }

    public function test_it_returns_a_zero_rate_when_there_are_no_visitors(): void
    {
        $rate = (new OverviewMetricsCalculator)->newVisitorRate(
            ['new' => 0, 'returning' => 0],
            ['new' => 0, 'returning' => 0],
        );

        $this->assertSame(0.0, $rate->current);
    }
}
