<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

/**
 * Sanitises event props before storage: keep only finite scalar values, cap the
 * key count so a hostile client cannot amplify storage, and encode to JSON
 * without ever letting a bad value fail the encode. Pure and deterministic.
 */
final class PropsEncoder
{
    private const MAX_PROPS = 30;

    /**
     * @param  array<string, mixed>|null  $props
     */
    public function encode(?array $props): ?string
    {
        if ($props === null) {
            return null;
        }

        $clean = [];
        foreach ($props as $key => $value) {
            if (count($clean) >= self::MAX_PROPS) {
                break;
            }

            if ($value === null || $this->isStorableScalar($value)) {
                $clean[(string) $key] = $value;
            }
        }

        if ($clean === []) {
            return null;
        }

        // JSON_INVALID_UTF8_SUBSTITUTE keeps bad bytes from failing the encode;
        // the false guard covers any remaining edge so we never write a literal
        // "false" into the column.
        $json = json_encode($clean, JSON_INVALID_UTF8_SUBSTITUTE);

        return $json === false ? null : $json;
    }

    private function isStorableScalar(mixed $value): bool
    {
        // INF / NAN pass is_scalar() but json_encode() cannot represent them and
        // returns false, so reject non-finite floats up front.
        if (is_float($value)) {
            return is_finite($value);
        }

        return is_scalar($value);
    }
}
