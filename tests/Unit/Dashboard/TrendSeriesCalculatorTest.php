<?php

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Services\Dashboard\TrendSeriesCalculator;

it('builds a continuous zero-filled list of trend points', function () {
    $period = new Period(CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-03'), 90);
    $rows = [
        '2026-06-01' => ['sessions' => 5, 'pageviews' => 12],
        '2026-06-03' => ['sessions' => 2, 'pageviews' => 3],
    ];

    $points = (new TrendSeriesCalculator)->points($rows, $period);

    expect($points)->toHaveCount(3)
        ->and($points[0]->sessions)->toBe(5)
        ->and($points[0]->pageviews)->toBe(12)
        ->and($points[1]->sessions)->toBe(0)          // 06-02 zero-filled
        ->and($points[2]->pageviews)->toBe(3)
        ->and($points[0]->date->format('Y-m-d'))->toBe('2026-06-01');
});
