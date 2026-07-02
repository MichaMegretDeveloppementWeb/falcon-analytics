<?php

use Falcon\Analytics\Support\EnvScaffolder;

$defaults = [
    'ANALYTICS_ENABLED' => 'true',
    'ANALYTICS_ROUTE_PREFIX' => 'admin/analytics',
    'ANALYTICS_GEOIP_DATABASE' => '',
];

it('appends every missing key under a header', function () use ($defaults) {
    $block = EnvScaffolder::appendableBlock("APP_NAME=Test\n", $defaults);

    expect($block)->toContain('# Falcon Analytics')
        ->toContain('ANALYTICS_ENABLED=true')
        ->toContain('ANALYTICS_ROUTE_PREFIX=admin/analytics')
        ->toContain('ANALYTICS_GEOIP_DATABASE=')
        ->and($block)->toStartWith("\n");
});

it('returns an empty block when every key is already present', function () use ($defaults) {
    $contents = "ANALYTICS_ENABLED=false\nANALYTICS_ROUTE_PREFIX=x\nANALYTICS_GEOIP_DATABASE=/db.mmdb\n";

    expect(EnvScaffolder::appendableBlock($contents, $defaults))->toBe('');
});

it('appends only the missing keys', function () use ($defaults) {
    $block = EnvScaffolder::appendableBlock("ANALYTICS_ENABLED=true\n", $defaults);

    expect($block)->not->toContain('ANALYTICS_ENABLED=true')
        ->and($block)->toContain('ANALYTICS_ROUTE_PREFIX=admin/analytics')
        ->toContain('ANALYTICS_GEOIP_DATABASE=');
});

it('does not treat a longer key name as already present', function () {
    $block = EnvScaffolder::appendableBlock("ANALYTICS_ENABLED_LEGACY=true\n", ['ANALYTICS_ENABLED' => 'true']);

    expect($block)->toContain('ANALYTICS_ENABLED=true');
});
