<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

/**
 * Sanitises event props before storage: keep only finite scalar values, cap the
 * key count, key length, string value length and total encoded size so a
 * hostile client cannot amplify storage from the public endpoint, and encode to
 * JSON without ever letting a bad value fail the encode. Pure and deterministic.
 */
final class PropsEncoder
{
    private const MAX_PROPS = 30;

    private const MAX_KEY_LENGTH = 100;

    private const MAX_STRING_LENGTH = 500;

    /** Hard cap of the encoded JSON written to the column, in bytes. */
    private const MAX_JSON_BYTES = 8192;

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
                $clean[mb_substr((string) $key, 0, self::MAX_KEY_LENGTH)] = $this->truncate($value);
            }
        }

        return $this->encodeWithinBudget($clean);
    }

    /**
     * Encode the cleaned entries, dropping trailing ones until the JSON fits
     * the byte budget. Bounded: MAX_PROPS iterations at worst.
     *
     * @param  array<string, mixed>  $clean
     */
    private function encodeWithinBudget(array $clean): ?string
    {
        while ($clean !== []) {
            // JSON_INVALID_UTF8_SUBSTITUTE keeps bad bytes from failing the encode;
            // the false guard covers any remaining edge so we never write a literal
            // "false" into the column.
            $json = json_encode($clean, JSON_INVALID_UTF8_SUBSTITUTE);

            if ($json === false) {
                return null;
            }

            if (strlen($json) <= self::MAX_JSON_BYTES) {
                return $json;
            }

            array_pop($clean);
        }

        return null;
    }

    private function truncate(mixed $value): mixed
    {
        return is_string($value) ? mb_substr($value, 0, self::MAX_STRING_LENGTH) : $value;
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
