<?php

use Falcon\Analytics\Support\EnvScaffolder;

$groups = [
    [
        'comment' => ['Master switch.'],
        'entries' => ['ANALYTICS_ENABLED' => 'true'],
    ],
    [
        'comment' => ['GeoIP licence key.', 'Second comment line.'],
        'entries' => ['ANALYTICS_GEOIP_LICENSE_KEY' => '', 'ANALYTICS_GEOIP_DATABASE' => ''],
    ],
];

it('appends every missing key under the package header with its group comments', function () use ($groups) {
    $block = EnvScaffolder::appendableBlock("APP_NAME=Test\n", $groups);

    expect($block)->toContain('# --- Falcon Analytics')
        ->toContain('# Master switch.')
        ->toContain('# Second comment line.')
        ->toContain('ANALYTICS_ENABLED=true')
        ->toContain('ANALYTICS_GEOIP_LICENSE_KEY=')
        ->toContain('ANALYTICS_GEOIP_DATABASE=')
        ->and($block)->toStartWith("\n");
});

it('returns an empty block when every key is already present', function () use ($groups) {
    $contents = "ANALYTICS_ENABLED=false\nANALYTICS_GEOIP_LICENSE_KEY=abc\nANALYTICS_GEOIP_DATABASE=/db.mmdb\n";

    expect(EnvScaffolder::appendableBlock($contents, $groups))->toBe('');
});

it('appends only the missing keys and skips fully-present groups with their comments', function () use ($groups) {
    $block = EnvScaffolder::appendableBlock("ANALYTICS_ENABLED=true\nANALYTICS_GEOIP_LICENSE_KEY=abc\n", $groups);

    expect($block)->not->toContain('ANALYTICS_ENABLED')
        ->and($block)->not->toContain('# Master switch.')
        ->and($block)->toContain('# GeoIP licence key.')
        ->toContain('ANALYTICS_GEOIP_DATABASE=')
        ->and($block)->not->toContain('ANALYTICS_GEOIP_LICENSE_KEY=');
});

it('does not treat a longer key name as already present', function () {
    $block = EnvScaffolder::appendableBlock(
        "ANALYTICS_ENABLED_LEGACY=true\n",
        [['comment' => [], 'entries' => ['ANALYTICS_ENABLED' => 'true']]],
    );

    expect($block)->toContain('ANALYTICS_ENABLED=true');
});
