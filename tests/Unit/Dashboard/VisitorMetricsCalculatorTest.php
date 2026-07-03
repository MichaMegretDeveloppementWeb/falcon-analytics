<?php

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\DTOs\Dashboard\VisitorMetrics;
use Falcon\Analytics\Services\Dashboard\VisitorMetricsCalculator;

function threeDayPeriod(): Period
{
    return new Period(
        CarbonImmutable::parse('2026-06-01'),
        CarbonImmutable::parse('2026-06-03'),
        90,
    );
}

it('computes deltas, the returning split and the sessions-per-visitor ratio', function () {
    $metrics = (new VisitorMetricsCalculator)->compute(
        current: ['visitors' => 10, 'new' => 4, 'sessions' => 25],
        previous: ['visitors' => 5, 'new' => 2, 'sessions' => 10],
        daily: ['active' => [], 'new' => []],
        period: threeDayPeriod(),
    );

    expect($metrics)->toBeInstanceOf(VisitorMetrics::class)
        ->and($metrics->visitors->delta->current)->toBe(10.0)
        ->and($metrics->visitors->delta->previous)->toBe(5.0)
        ->and($metrics->newVisitors->delta->current)->toBe(4.0)
        ->and($metrics->returning->delta->current)->toBe(6.0)            // 10 - 4
        ->and($metrics->returning->delta->previous)->toBe(3.0)           // 5 - 2
        ->and($metrics->sessionsPerVisitor->delta->current)->toBe(2.5)   // 25 / 10
        ->and($metrics->sessionsPerVisitor->delta->previous)->toBe(2.0); // 10 / 5
});

it('builds zero-filled daily series with clamped new and derived returning', function () {
    $daily = [
        'active' => [
            '2026-06-01' => ['sessions' => 6, 'visitors' => 3],
            '2026-06-03' => ['sessions' => 4, 'visitors' => 2],
        ],
        'new' => ['2026-06-01' => 2, '2026-06-03' => 5], // 5 > 2 active → clamped
    ];

    $metrics = (new VisitorMetricsCalculator)->compute(
        current: ['visitors' => 5, 'new' => 4, 'sessions' => 10],
        previous: ['visitors' => 0, 'new' => 0, 'sessions' => 0],
        daily: $daily,
        period: threeDayPeriod(),
    );

    // 3 days: 06-01, 06-02 (empty), 06-03.
    expect($metrics->visitors->sparkline)->toBe([3.0, 0.0, 2.0])
        ->and($metrics->newVisitors->sparkline)->toBe([2.0, 0.0, 2.0])
        ->and($metrics->returning->sparkline)->toBe([1.0, 0.0, 0.0])
        ->and($metrics->sessionsPerVisitor->sparkline)->toBe([2.0, 0.0, 2.0]);
});

it('never divides by zero when a period has no visitors', function () {
    $zero = ['visitors' => 0, 'new' => 0, 'sessions' => 0];

    $metrics = (new VisitorMetricsCalculator)->compute(
        current: $zero,
        previous: $zero,
        daily: ['active' => [], 'new' => []],
        period: new Period(CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-01'), 90),
    );

    expect($metrics->sessionsPerVisitor->delta->current)->toBe(0.0)
        ->and($metrics->sessionsPerVisitor->sparkline)->toBe([0.0]);
});
