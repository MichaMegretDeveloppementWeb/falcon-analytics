<?php

use Falcon\Analytics\DTOs\Dashboard\MetricDelta;

it('has a baseline only when the previous value is non-zero', function () {
    expect((new MetricDelta(10, 5))->hasBaseline())->toBeTrue()
        ->and((new MetricDelta(10, 0))->hasBaseline())->toBeFalse();
});

it('computes the period-over-period percentage change rounded to one decimal', function () {
    expect((new MetricDelta(150, 100))->changePercent())->toBe(50.0)
        ->and((new MetricDelta(75, 100))->changePercent())->toBe(-25.0)
        ->and((new MetricDelta(1, 3))->changePercent())->toBe(-66.7);
});

it('returns a zero change when there is no baseline, never dividing by zero', function () {
    expect((new MetricDelta(42, 0))->changePercent())->toBe(0.0);
});

it('reports whether the metric increased', function () {
    expect((new MetricDelta(10, 5))->increased())->toBeTrue()
        ->and((new MetricDelta(5, 10))->increased())->toBeFalse()
        ->and((new MetricDelta(5, 5))->increased())->toBeFalse();
});
