<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

use Illuminate\Support\Str;

/**
 * Human, translated wording for an acquisition channel, shared by every screen
 * (and by the source Blade component) so the same channel never reads two
 * different ways: `for()` names it, `description()` says what it means.
 *
 * @internal
 */
final class SourceLabel
{
    public static function for(?string $source): string
    {
        return match (self::key($source)) {
            'direct', '' => __('Direct'),
            'organic' => __('Recherche naturelle'),
            'social' => __('Social naturel'),
            'paid' => __('Payant'),
            'referral', 'campaign' => __('Référent'),
            'email' => __('E-mail'),
            default => Str::headline((string) $source),
        };
    }

    /**
     * The line shown under the label: where the visit came from, in words the
     * label does not already carry.
     */
    public static function description(?string $source): string
    {
        return match (self::key($source)) {
            // An absent source is the direct channel, as `for()` decides: the
            // line under the label cannot answer "unknown" to it.
            'direct', '' => __('Accès direct'),
            'organic' => __('Recherche naturelle'),
            'social' => __('Social naturel'),
            'paid' => __('Trafic publicitaire'),
            'referral', 'campaign' => __('Site référent'),
            'email' => __('Campagne e-mail'),
            default => __('Provenance inconnue'),
        };
    }

    private static function key(?string $source): string
    {
        return $source !== null ? strtolower($source) : '';
    }
}
