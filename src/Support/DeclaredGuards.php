<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

/**
 * The guards a configuration list names, kept to those the host declares, so a
 * stray name never triggers a runtime error.
 *
 * @internal
 */
final class DeclaredGuards
{
    /**
     * @param  'subject_guards'|'exclude_guards'  $key
     * @return list<string>
     */
    public static function of(string $key): array
    {
        $defined = config('auth.guards', []);

        return array_values(array_filter(
            (array) config("analytics.identity.{$key}", []),
            fn (mixed $guard): bool => is_string($guard) && is_array($defined) && array_key_exists($guard, $defined),
        ));
    }
}
