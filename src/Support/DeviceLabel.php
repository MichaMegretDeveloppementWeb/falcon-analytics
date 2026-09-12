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
        // Normalised once, and it is that value the last case uses: an absent
        // type then falls on the empty case, and the default can no longer
        // receive null. `Str::title` gives the same label either way, its
        // conversion already going through a lowercasing.
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
