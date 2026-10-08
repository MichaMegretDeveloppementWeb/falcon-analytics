<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Services\Dashboard\MarketingMetricsCalculator;
use Falcon\Analytics\Tests\TestCase;

final class MarketingMetricsCalculatorTest extends TestCase
{
    public function test_it_computes_and_formats_the_conversion_rate_guarding_against_a_zero_denominator(): void
    {
        $calculator = new MarketingMetricsCalculator;

        $this->assertSame(25.0, $calculator->rate(3.0, 12.0));
        $this->assertSame(0.0, $calculator->rate(1.0, 0.0));
        $this->assertStringContainsString('25,0', $calculator->rateLabel(25.0));
    }

    public function test_it_zero_fills_the_daily_trend_and_derives_the_per_day_rate(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $trend = (new MarketingMetricsCalculator)->trend(
            Period::ofDays(7),
            ['2026-06-15' => 10],
            ['2026-06-15' => 2],
            ['2026-06-15' => 7],
        );

        // One entry per day, today last with its data and its derived rate.
        $this->assertCount(count($trend['sessions']), $trend['labels']);
        $this->assertSame(10, end($trend['sessions']));
        $this->assertSame(7, end($trend['visitors']));
        $this->assertSame(0, $trend['visitors'][0]);
        $this->assertSame(2, end($trend['conversions']));
        $this->assertSame(0, $trend['sessions'][0]);
        $this->assertSame(0.0, $trend['rates'][0]);
    }

    /**
     * The line under the rate says what the rate says · people who converted
     * over people who came that day, never over their sessions. A visitor who
     * came back three times is one visitor, as in the headline figure.
     */
    public function test_a_day_of_the_rate_counts_people_like_the_figure_above_it(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
        $calculator = new MarketingMetricsCalculator;

        $trend = $calculator->trend(Period::ofDays(7), ['2026-06-15' => 10], ['2026-06-15' => 2], ['2026-06-15' => 4]);

        $this->assertSame(round($calculator->rate(2.0, 4.0), 1), end($trend['rates']));
        $this->assertSame(50.0, end($trend['rates']));
    }

    /** Over days whose visitors are no longer known, the line stays empty rather than drawn on sessions. */
    public function test_the_rate_line_is_empty_when_the_visitors_are_unknown(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $trend = (new MarketingMetricsCalculator)->trend(Period::ofDays(7), ['2026-06-15' => 10], ['2026-06-15' => 2], null);

        $this->assertSame([], $trend['rates']);
        $this->assertSame([], $trend['visitors']);
    }
}
