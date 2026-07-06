<?php

use Falcon\Analytics\Events\EventRegistry;

it('loads tracked events declared in the configured file', function () {
    config(['analytics.events_path' => __DIR__.'/../Fixtures/analytics-events.php']);
    $this->app->forgetInstance(EventRegistry::class);

    $registry = app(EventRegistry::class);

    expect($registry->all())->toHaveCount(2)
        ->and($registry->names())->toBe(['sample.action', 'sample.other']);

    $event = $registry->get('sample.action');

    expect($event)->not->toBeNull()
        ->and($event->label)->toBe('Sample action')
        ->and($event->value)->toBe(5.0)
        ->and($registry->get('sample.other')->value)->toBeNull()
        ->and($registry->get('unknown'))->toBeNull();
});
