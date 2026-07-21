<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

/**
 * Builds the env block `analytics:install` appends to the host's .env files.
 * The published config file stays the source of truth (every value has a safe
 * default); the scaffold only exists so the supported variables are
 * discoverable in place, commented, without hunting through vendor config.
 * Append-only and idempotent: existing keys are never rewritten, present keys
 * are never duplicated, and nothing runs outside the install command.
 */
final class EnvScaffolder
{
    /**
     * The text block to append for the entries missing from the given env
     * contents: a package header, then each group's comment lines and its
     * missing keys. Groups whose keys are all present are skipped entirely;
     * an empty string means the file is already complete and the caller can
     * skip the write.
     *
     * @param  list<array{comment: list<string>, entries: array<string, string>}>  $groups
     */
    public static function appendableBlock(string $contents, array $groups): string
    {
        $sections = [];

        foreach ($groups as $group) {
            $missing = array_filter(
                $group['entries'],
                fn (string $key): bool => ! preg_match('/^'.preg_quote($key, '/').'=/m', $contents),
                ARRAY_FILTER_USE_KEY,
            );

            if ($missing === []) {
                continue;
            }

            $lines = array_map(fn (string $comment): string => '# '.$comment, $group['comment']);

            foreach ($missing as $key => $value) {
                $lines[] = $key.'='.$value;
            }

            $sections[] = implode("\n", $lines);
        }

        if ($sections === []) {
            return '';
        }

        $header = '# --- Falcon Analytics '.str_repeat('-', 55);

        return "\n".$header."\n\n".implode("\n\n", $sections)."\n";
    }
}
