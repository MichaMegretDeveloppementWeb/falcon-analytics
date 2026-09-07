<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Unit\Dashboard;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Services\Dashboard\TrendSeriesCalculator;
use PHPUnit\Framework\TestCase;

final class TrendSeriesCalculatorTest extends TestCase
{
    public function test_it_builds_a_continuous_zero_filled_list_of_trend_points(): void
    {
        $period = new Period(CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-03'), 90);
        $rows = [
            '2026-06-01' => ['sessions' => 5, 'pageviews' => 12],
            '2026-06-03' => ['sessions' => 2, 'pageviews' => 3],
        ];

        $points = (new TrendSeriesCalculator)->points($rows, $period);

        $this->assertCount(3, $points);
        $this->assertSame(5, $points[0]->sessions);
        $this->assertSame(12, $points[0]->pageviews);
        $this->assertSame(0, $points[1]->sessions, '06-02 est comblé à zéro.');
        $this->assertSame(3, $points[2]->pageviews);
        $this->assertSame('2026-06-01', $points[0]->date->format('Y-m-d'));
    }
}
