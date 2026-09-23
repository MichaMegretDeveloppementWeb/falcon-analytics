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
            'referral' => __('Référent'),
            'campaign' => __('Lien de campagne'),
            'email' => __('E-mail'),
            default => Str::headline((string) $source),
        };
    }

    /**
     * The line shown under the label: how the visit arrived, in words the
     * label does not already carry.
     */
    public static function description(?string $source): string
    {
        return match (self::key($source)) {
            // An absent source is the direct channel, as `for()` decides: the
            // line under the label cannot answer "unknown" to it.
            'direct', '' => __('Adresse saisie, favori, ou lien sans origine connue'),
            'organic' => __('Depuis un moteur de recherche, hors annonce'),
            'social' => __('Depuis un réseau social, hors publicité'),
            'paid' => __('Depuis une annonce payante'),
            'referral' => __('Depuis un lien sur un autre site'),
            'campaign' => __('Lien de campagne dont le support n\'est pas reconnu (affiche, QR code…)'),
            'email' => __('Depuis un lien dans un e-mail'),
            default => __('Provenance inconnue'),
        };
    }

    private static function key(?string $source): string
    {
        return $source !== null ? strtolower($source) : '';
    }
}
