<?php

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Services\Dashboard\MarketingMetricsCalculator;

it('computes and formats the conversion rate, guarding against a zero denominator', function () {
    $calc = new MarketingMetricsCalculator;

    expect($calc->rate(3.0, 12.0))->toBe(25.0)
        ->and($calc->rate(1.0, 0.0))->toBe(0.0)
        ->and($calc->rateLabel(25.0))->toContain('25,0');
});

it('zero-fills the daily trend and derives the per-day rate', function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

    $trend = (new MarketingMetricsCalculator)->trend(
        Period::ofDays(7),
        ['2026-06-15' => 10],
        ['2026-06-15' => 2],
    );

    // One entry per day, the last being today with its data and derived rate (2/10).
    expect($trend['labels'])->toHaveCount(count($trend['sessions']))
        ->and(end($trend['sessions']))->toBe(10)
        ->and(end($trend['conversions']))->toBe(2)
        ->and(end($trend['rates']))->toBe(20.0)
        ->and($trend['sessions'][0])->toBe(0)
        ->and($trend['rates'][0])->toBe(0.0);
});
