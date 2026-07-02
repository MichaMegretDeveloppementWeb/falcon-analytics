<?php

use Falcon\Analytics\Facades\Analytics;
use Illuminate\Support\Facades\Blade;

it('renders the collector config and cached script when enabled', function () {
    config(['analytics.enabled' => true, 'analytics.endpoint' => '__analytics']);

    $html = Blade::render('@analyticsScripts');

    expect($html)->toContain('window.__falconAnalytics')
        ->toContain('__analytics')
        ->toContain('__analytics.js')
        ->toContain('defer');
});

it('renders nothing when disabled', function () {
    config(['analytics.enabled' => false]);

    expect(Blade::render('@analyticsScripts'))->toBe('');
});

it('renders nothing for an excluded context', function () {
    config(['analytics.enabled' => true]);
    Analytics::excludeUsing(fn () => true);

    expect(Blade::render('@analyticsScripts'))->toBe('');
});
