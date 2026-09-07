<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Unit\Dashboard;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Services\Dashboard\EngagementMetricsCalculator;
use PHPUnit\Framework\TestCase;

final class EngagementMetricsCalculatorTest extends TestCase
{
    /**
     * @return array<string, int|float>
     */
    private function counts(int $visitors, int $sessions, int $pageviews, float $avgSeconds, int $bounces): array
    {
        return compact('visitors', 'sessions', 'pageviews', 'avgSeconds', 'bounces');
    }

    public function test_it_computes_headline_deltas_and_ratios_from_raw_counts(): void
    {
        $headline = (new EngagementMetricsCalculator)->headline(
            $this->counts(10, 20, 50, 60.0, 5),
            $this->counts(5, 10, 20, 40.0, 4),
        );

        $this->assertSame(20.0, $headline['sessions']->current);
        $this->assertSame(100.0, $headline['sessions']->changePercent());
        $this->assertSame(60.0, $headline['avgSeconds']->current);
        $this->assertSame(2.5, $headline['pagesPerSession']->current, '50 / 20');
        $this->assertSame(25.0, $headline['bounceRate']->current, '5 / 20 * 100');
    }

    public function test_it_computes_the_spotlight_today_and_yesterday_ratios(): void
    {
        $spotlight = (new EngagementMetricsCalculator)->spotlight(
            $this->counts(3, 6, 12, 30.0, 2),
            $this->counts(1, 2, 4, 20.0, 1),
        );

        $this->assertSame(['today' => 6.0, 'yesterday' => 2.0], $spotlight['sessions']);
        $this->assertSame(2 / 6 * 100, $spotlight['bounceRate']['today']);
    }

    public function test_it_builds_zero_filled_sparkline_series_from_daily_rows(): void
    {
        $period = new Period(CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-03'), 90);
        $rows = [
            '2026-06-01' => ['sessions' => 4, 'visitors' => 3, 'pageviews' => 8, 'avgSeconds' => 30.0, 'bounces' => 1],
            '2026-06-03' => ['sessions' => 2, 'visitors' => 2, 'pageviews' => 2, 'avgSeconds' => 10.0, 'bounces' => 2],
        ];

        $series = (new EngagementMetricsCalculator)->sparklines($rows, $period);

        $this->assertSame([4.0, 0.0, 2.0], $series['sessions']);
        $this->assertSame([2.0, 0.0, 1.0], $series['pagesPerSession'], '8/4, 0, 2/2');
        $this->assertSame([25.0, 0.0, 100.0], $series['bounceRate'], '1/4, 0, 2/2 en pourcentage');
    }
}
