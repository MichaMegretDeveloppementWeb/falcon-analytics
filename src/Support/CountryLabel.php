<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

use Locale;

/**
 * A country named in the package's language from its two-letter code.
 *
 * @internal
 */
final class CountryLabel
{
    /** The country's name, the code itself when intl cannot name it, null without a code. */
    public static function for(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        $code = strtoupper($code);

        if (strlen($code) !== 2 || ! ctype_alpha($code) || ! class_exists(Locale::class)) {
            return $code;
        }

        $name = Locale::getDisplayRegion('-'.$code, Language::CODE);

        return is_string($name) && $name !== '' && strtoupper($name) !== $code ? $name : $code;
    }
}
