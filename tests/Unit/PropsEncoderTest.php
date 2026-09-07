<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Unit;

use Falcon\Analytics\Support\PropsEncoder;
use PHPUnit\Framework\TestCase;

final class PropsEncoderTest extends TestCase
{
    public function test_it_keeps_finite_scalars_and_drops_non_scalar_values(): void
    {
        $json = (new PropsEncoder)->encode(['a' => 1, 'b' => 'x', 'c' => true, 'd' => ['nested'], 'e' => null]);

        $this->assertSame(['a' => 1, 'b' => 'x', 'c' => true, 'e' => null], json_decode($json, true));
    }

    public function test_it_rejects_non_finite_floats_instead_of_failing_the_encode(): void
    {
        $json = (new PropsEncoder)->encode(['ratio' => INF, 'other' => NAN, 'ok' => 1.5]);

        $this->assertSame(['ok' => 1.5], json_decode($json, true));
    }

    public function test_it_returns_null_when_every_value_is_dropped(): void
    {
        $encoder = new PropsEncoder;

        $this->assertNull($encoder->encode(['only' => ['array'], 'inf' => INF]));
        $this->assertNull($encoder->encode([]));
        $this->assertNull($encoder->encode(null));
    }

    public function test_it_caps_the_number_of_stored_keys(): void
    {
        $props = [];

        foreach (range(1, 50) as $i) {
            $props["k{$i}"] = $i;
        }

        $this->assertCount(30, json_decode((new PropsEncoder)->encode($props), true));
    }

    public function test_it_truncates_oversized_keys_and_string_values(): void
    {
        $json = (new PropsEncoder)->encode([
            str_repeat('k', 300) => 'v',
            'text' => str_repeat('x', 2_000),
            'number' => 12345,
        ]);

        $decoded = json_decode($json, true);

        $this->assertSame(str_repeat('k', 100), array_key_first($decoded));
        $this->assertSame(str_repeat('x', 500), $decoded['text']);
        $this->assertSame(12345, $decoded['number']);
    }

    public function test_it_caps_the_encoded_json_size_by_dropping_trailing_entries(): void
    {
        $props = [];

        foreach (range(1, 30) as $i) {
            $props[sprintf('key_%02d', $i)] = str_repeat('v', 500);
        }

        $json = (new PropsEncoder)->encode($props);
        $decoded = json_decode($json, true);

        $this->assertLessThanOrEqual(8192, strlen($json));
        $this->assertNotEmpty($decoded);

        // Les entrées gardées sont les premières, dans l'ordre.
        $this->assertSame('key_01', array_key_first($decoded));
        $this->assertLessThan(30, count($decoded));
    }
}
