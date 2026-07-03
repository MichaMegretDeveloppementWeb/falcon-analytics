<?php

use Falcon\Analytics\Services\Dashboard\OverviewMetricsCalculator;

it('computes the new-visitor rate period over period', function () {
    $rate = (new OverviewMetricsCalculator)->newVisitorRate(
        ['new' => 3, 'returning' => 1], // 75 %
        ['new' => 1, 'returning' => 1], // 50 %
    );

    expect($rate->current)->toBe(75.0)
        ->and($rate->previous)->toBe(50.0);
});

it('returns a zero rate when there are no visitors', function () {
    $rate = (new OverviewMetricsCalculator)->newVisitorRate(
        ['new' => 0, 'returning' => 0],
        ['new' => 0, 'returning' => 0],
    );

    expect($rate->current)->toBe(0.0);
});
