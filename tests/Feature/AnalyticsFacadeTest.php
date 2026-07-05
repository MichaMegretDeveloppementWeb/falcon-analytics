<?php

use Falcon\Analytics\Analytics;
use Falcon\Analytics\Facades\Analytics as AnalyticsFacade;

it('resolves the manager as a shared singleton', function () {
    expect(app(Analytics::class))->toBe(app(Analytics::class));
});

it('registers resolvers through the facade onto the container instance', function () {
    AnalyticsFacade::consentUsing(fn () => true);
    AnalyticsFacade::resolveSubjectUsing(fn () => ['type' => 'client', 'id' => 5]);

    expect(AnalyticsFacade::hasConsent())->toBeTrue()
        ->and(app(Analytics::class)->hasConsent())->toBeTrue()
        ->and(app(Analytics::class)->subject())->toBe(['type' => 'client', 'id' => 5]);
});
