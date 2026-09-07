<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Unit\Dashboard;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\DTOs\Dashboard\VisitorMetrics;
use Falcon\Analytics\Services\Dashboard\VisitorMetricsCalculator;
use PHPUnit\Framework\TestCase;

final class VisitorMetricsCalculatorTest extends TestCase
{
    private function threeDayPeriod(): Period
    {
        return new Period(
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-03'),
            90,
        );
    }

    public function test_it_computes_deltas_the_returning_split_and_the_sessions_per_visitor_ratio(): void
    {
        $metrics = (new VisitorMetricsCalculator)->compute(
            current: ['visitors' => 10, 'new' => 4, 'sessions' => 25],
            previous: ['visitors' => 5, 'new' => 2, 'sessions' => 10],
            daily: ['active' => [], 'new' => []],
            period: $this->threeDayPeriod(),
        );

        $this->assertInstanceOf(VisitorMetrics::class, $metrics);
        $this->assertSame(10.0, $metrics->visitors->delta->current);
        $this->assertSame(5.0, $metrics->visitors->delta->previous);
        $this->assertSame(4.0, $metrics->newVisitors->delta->current);
        $this->assertSame(6.0, $metrics->returning->delta->current, '10 - 4');
        $this->assertSame(3.0, $metrics->returning->delta->previous, '5 - 2');
        $this->assertSame(2.5, $metrics->sessionsPerVisitor->delta->current, '25 / 10');
        $this->assertSame(2.0, $metrics->sessionsPerVisitor->delta->previous, '10 / 5');
    }

    public function test_it_builds_zero_filled_daily_series_with_clamped_new_and_derived_returning(): void
    {
        $daily = [
            'active' => [
                '2026-06-01' => ['sessions' => 6, 'visitors' => 3],
                '2026-06-03' => ['sessions' => 4, 'visitors' => 2],
            ],
            'new' => ['2026-06-01' => 2, '2026-06-03' => 5], // 5 > 2 actifs, donc borné
        ];

        $metrics = (new VisitorMetricsCalculator)->compute(
            current: ['visitors' => 5, 'new' => 4, 'sessions' => 10],
            previous: ['visitors' => 0, 'new' => 0, 'sessions' => 0],
            daily: $daily,
            period: $this->threeDayPeriod(),
        );

        // Trois jours : 06-01, 06-02 (vide), 06-03.
        $this->assertSame([3.0, 0.0, 2.0], $metrics->visitors->sparkline);
        $this->assertSame([2.0, 0.0, 2.0], $metrics->newVisitors->sparkline);
        $this->assertSame([1.0, 0.0, 0.0], $metrics->returning->sparkline);
        $this->assertSame([2.0, 0.0, 2.0], $metrics->sessionsPerVisitor->sparkline);
    }

    public function test_it_never_divides_by_zero_when_a_period_has_no_visitors(): void
    {
        $zero = ['visitors' => 0, 'new' => 0, 'sessions' => 0];

        $metrics = (new VisitorMetricsCalculator)->compute(
            current: $zero,
            previous: $zero,
            daily: ['active' => [], 'new' => []],
            period: new Period(CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-01'), 90),
        );

        $this->assertSame(0.0, $metrics->sessionsPerVisitor->delta->current);
        $this->assertSame([0.0], $metrics->sessionsPerVisitor->sparkline);
    }
}
