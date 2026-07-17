<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

use Illuminate\Support\Str;

/**
 * Human, translated label for an acquisition channel, shared by every screen
 * (and by the source Blade component) so the same channel never reads two
 * different ways.
 */
final class SourceLabel
{
    public static function for(?string $source): string
    {
        return match ($source !== null ? strtolower($source) : '') {
            'direct' => __('Direct'),
            'organic' => __('Recherche naturelle'),
            'social' => __('Social naturel'),
            'paid' => __('Payant'),
            'referral', 'campaign' => __('Référent'),
            'email' => __('E-mail'),
            '' => __('Direct'),
            default => Str::headline((string) $source),
        };
    }
}
