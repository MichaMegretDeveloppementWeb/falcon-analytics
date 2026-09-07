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
        );

        // Une entrée par jour, la dernière étant aujourd'hui avec ses données
        // et son taux dérivé (2/10).
        $this->assertCount(count($trend['sessions']), $trend['labels']);
        $this->assertSame(10, end($trend['sessions']));
        $this->assertSame(2, end($trend['conversions']));
        $this->assertSame(20.0, end($trend['rates']));
        $this->assertSame(0, $trend['sessions'][0]);
        $this->assertSame(0.0, $trend['rates'][0]);
    }
}
