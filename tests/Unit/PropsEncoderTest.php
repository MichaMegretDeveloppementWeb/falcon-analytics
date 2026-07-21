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

it('truncates oversized keys and string values', function () {
    $json = (new PropsEncoder)->encode([
        str_repeat('k', 300) => 'v',
        'text' => str_repeat('x', 2_000),
        'number' => 12345,
    ]);

    $decoded = json_decode($json, true);

    expect(array_key_first($decoded))->toBe(str_repeat('k', 100))
        ->and($decoded['text'])->toBe(str_repeat('x', 500))
        ->and($decoded['number'])->toBe(12345);
});

it('caps the encoded JSON size by dropping trailing entries', function () {
    $props = [];
    foreach (range(1, 30) as $i) {
        $props[sprintf('key_%02d', $i)] = str_repeat('v', 500);
    }

    $json = (new PropsEncoder)->encode($props);
    $decoded = json_decode($json, true);

    expect(strlen($json))->toBeLessThanOrEqual(8192)
        ->and($decoded)->not->toBeEmpty()
        // The kept entries are the first ones, in order.
        ->and(array_key_first($decoded))->toBe('key_01')
        ->and(count($decoded))->toBeLessThan(30);
});
