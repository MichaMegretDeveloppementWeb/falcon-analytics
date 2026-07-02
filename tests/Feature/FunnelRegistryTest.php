<?php

use Falcon\Analytics\Funnels\Funnel;
use Falcon\Analytics\Funnels\FunnelRegistry;

it('loads funnels declared in the configured file', function () {
    config(['analytics.funnels_path' => __DIR__.'/../Fixtures/analytics-funnels.php']);
    $this->app->forgetInstance(FunnelRegistry::class);

    $registry = app(FunnelRegistry::class);

    expect($registry->all())->toHaveCount(1);

    $funnel = $registry->get('sample');

    expect($funnel)->not->toBeNull()
        ->and($funnel->label)->toBe('Sample funnel')
        ->and($funnel->steps())->toHaveCount(2)
        ->and($funnel->steps()[0]->route)->toBe('home')
        ->and($funnel->steps()[0]->event)->toBeNull()
        ->and($funnel->steps()[0]->value)->toBe(1.0)
        ->and($funnel->steps()[1]->event)->toBe('sample.action')
        ->and($funnel->steps()[1]->value)->toBe(5.0);
});

it('rejects a step matching neither or both of event and route', function () {
    $funnel = new Funnel('x', 'X');

    expect(fn () => $funnel->step('bad', value: 1))->toThrow(InvalidArgumentException::class);
    expect(fn () => $funnel->step('bad', value: 1, event: 'e', route: 'r'))->toThrow(InvalidArgumentException::class);
});

it('degrades without crashing when the funnels file throws', function () {
    config(['analytics.funnels_path' => __DIR__.'/../Fixtures/analytics-funnels-broken.php']);
    $this->app->forgetInstance(FunnelRegistry::class);

    $registry = app(FunnelRegistry::class);

    // The file declares one funnel then throws; resolution must not break.
    expect($registry->all())->toHaveCount(1)
        ->and($registry->get('before'))->not->toBeNull();
});
