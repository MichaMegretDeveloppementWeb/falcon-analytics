<?php

declare(strict_types=1);

namespace Falcon\Analytics\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void resolveSubjectUsing(Closure $resolver)
 * @method static void consentUsing(Closure $resolver)
 * @method static void excludeUsing(Closure $resolver)
 * @method static array{type: string, id: int}|null subject()
 * @method static bool hasConsent()
 * @method static bool isExcluded()
 *
 * @see \Falcon\Analytics\Analytics
 */
final class Analytics extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Falcon\Analytics\Analytics::class;
    }
}
