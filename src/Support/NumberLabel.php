<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

use Illuminate\Support\Facades\App;
use LogicException;
use NumberFormatter;

/**
 * Every number the package shows, written once · in the site's language,
 * through intl, rounded half up, one formatter kept per language, style and
 * precision.
 *
 * @internal
 */
final class NumberLabel
{
    /** @var array<string, NumberFormatter> */
    private static array $formatters = [];

    public static function for(int|float $number, int $decimals = 0): string
    {
        return self::written(self::formatter(NumberFormatter::DECIMAL, $decimals), $number);
    }

    /** A score with its unit, singular below two · « 1 pt », « 12 pts ». */
    public static function points(int $points): string
    {
        return self::for($points)."\u{00A0}".(abs($points) < 2 ? __('pt') : __('pts'));
    }

    /** A share already expressed in percent · 45.3 reads « 45,3 % ». */
    public static function percent(int|float $percent, int $decimals = 0): string
    {
        return self::written(self::formatter(NumberFormatter::PERCENT, $decimals), $percent / 100);
    }

    private static function formatter(int $style, int $decimals): NumberFormatter
    {
        $locale = App::getLocale();

        return self::$formatters["{$locale}:{$style}:{$decimals}"] ??= self::made($locale, $style, $decimals);
    }

    private static function made(string $locale, int $style, int $decimals): NumberFormatter
    {
        $formatter = new NumberFormatter($locale, $style);
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $decimals);
        $formatter->setAttribute(NumberFormatter::ROUNDING_MODE, NumberFormatter::ROUND_HALFUP);

        return $formatter;
    }

    private static function written(NumberFormatter $formatter, int|float $number): string
    {
        $written = $formatter->format($number);

        if ($written === false) {
            throw new LogicException('The number could not be written: '.$formatter->getErrorMessage());
        }

        return $written;
    }
}
