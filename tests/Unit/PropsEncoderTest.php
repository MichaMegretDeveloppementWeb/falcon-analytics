<?php

use Falcon\Analytics\Support\PropsEncoder;

it('keeps finite scalars and drops non-scalar values', function () {
    $json = (new PropsEncoder)->encode(['a' => 1, 'b' => 'x', 'c' => true, 'd' => ['nested'], 'e' => null]);

    expect(json_decode($json, true))->toBe(['a' => 1, 'b' => 'x', 'c' => true, 'e' => null]);
});

it('rejects non-finite floats (INF / NAN) instead of failing the encode', function () {
    $json = (new PropsEncoder)->encode(['ratio' => INF, 'other' => NAN, 'ok' => 1.5]);

    expect(json_decode($json, true))->toBe(['ok' => 1.5]);
});

it('returns null when every value is dropped', function () {
    $encoder = new PropsEncoder;

    expect($encoder->encode(['only' => ['array'], 'inf' => INF]))->toBeNull()
        ->and($encoder->encode([]))->toBeNull()
        ->and($encoder->encode(null))->toBeNull();
});

it('caps the number of stored keys', function () {
    $props = [];
    foreach (range(1, 50) as $i) {
        $props["k{$i}"] = $i;
    }

    expect(json_decode((new PropsEncoder)->encode($props), true))->toHaveCount(30);
});
