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
        return match ($type !== null ? strtolower($type) : '') {
            'desktop' => __('Ordinateur'),
            'mobile' => __('Mobile'),
            'tablet' => __('Tablette'),
            '' => __('Inconnu'),
            default => Str::title($type),
        };
    }
}
