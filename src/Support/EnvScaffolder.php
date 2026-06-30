<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

final class EnvScaffolder
{
    /**
     * Build the text block to append to an env file for the entries that are
     * missing from it. Returns an empty string when every key is already set,
     * so the caller can skip the write and stay idempotent.
     *
     * @param  array<string, string>  $entries
     */
    public static function appendableBlock(string $contents, array $entries): string
    {
        $missing = array_filter(
            $entries,
            fn (string $key): bool => ! preg_match('/^'.preg_quote($key, '/').'=/m', $contents),
            ARRAY_FILTER_USE_KEY,
        );

        if ($missing === []) {
            return '';
        }

        $lines = ['', '# Falcon Analytics'];

        foreach ($missing as $key => $value) {
            $lines[] = $key.'='.$value;
        }

        return implode("\n", $lines)."\n";
    }
}
