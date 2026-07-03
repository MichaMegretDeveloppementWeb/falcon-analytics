<?php

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Services\Dashboard\EngagementMetricsCalculator;

function engagementCounts(int $visitors, int $sessions, int $pageviews, float $avgSeconds, int $bounces): array
{
    return compact('visitors', 'sessions', 'pageviews', 'avgSeconds', 'bounces');
}

it('computes headline deltas and ratios from raw counts', function () {
    $headline = (new EngagementMetricsCalculator)->headline(
        engagementCounts(10, 20, 50, 60.0, 5),
        engagementCounts(5, 10, 20, 40.0, 4),
    );

    expect($headline['sessions']->current)->toBe(20.0)
        ->and($headline['sessions']->changePercent())->toBe(100.0)
        ->and($headline['avgSeconds']->current)->toBe(60.0)
        ->and($headline['pagesPerSession']->current)->toBe(2.5)  // 50 / 20
        ->and($headline['bounceRate']->current)->toBe(25.0);     // 5 / 20 * 100
});

it('computes the spotlight today and yesterday ratios', function () {
    $spotlight = (new EngagementMetricsCalculator)->spotlight(
        engagementCounts(3, 6, 12, 30.0, 2),
        engagementCounts(1, 2, 4, 20.0, 1),
    );

    expect($spotlight['sessions'])->toBe(['today' => 6.0, 'yesterday' => 2.0])
        ->and($spotlight['bounceRate']['today'])->toBe(2 / 6 * 100);
});

it('builds zero-filled sparkline series from daily rows', function () {
    $period = new Period(CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-03'), 90);
    $rows = [
        '2026-06-01' => ['sessions' => 4, 'visitors' => 3, 'pageviews' => 8, 'avgSeconds' => 30.0, 'bounces' => 1],
        '2026-06-03' => ['sessions' => 2, 'visitors' => 2, 'pageviews' => 2, 'avgSeconds' => 10.0, 'bounces' => 2],
    ];

    $series = (new EngagementMetricsCalculator)->sparklines($rows, $period);

    expect($series['sessions'])->toBe([4.0, 0.0, 2.0])
        ->and($series['pagesPerSession'])->toBe([2.0, 0.0, 1.0])   // 8/4, 0, 2/2
        ->and($series['bounceRate'])->toBe([25.0, 0.0, 100.0]);    // 1/4, 0, 2/2 as %
});
