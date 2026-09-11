<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

use Illuminate\Support\Str;

/**
 * Human, translated label for a device type, shared by every screen so the same
 * device never reads "Ordinateur" on one and "Desktop" on another.
 */
final class DeviceLabel
{
    public static function for(?string $type): string
    {
        // Normalise une fois, et c'est cette valeur que le dernier cas emploie ·
        // un type absent tombe alors sur le cas vide, et le defaut ne peut plus
        // recevoir null. `Str::title` rend le meme libelle dans les deux cas,
        // sa conversion passant deja par une mise en minuscules.
        $normalised = $type !== null ? strtolower($type) : '';

        return match ($normalised) {
            'desktop' => __('Ordinateur'),
            'mobile' => __('Mobile'),
            'tablet' => __('Tablette'),
            '' => __('Inconnu'),
            default => Str::title($normalised),
        };
    }
}
