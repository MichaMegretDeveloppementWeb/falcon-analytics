<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

use Illuminate\Support\Str;

/**
 * The device a session was opened on, named and drawn the same way on every
 * screen · from the names the user-agent library records, which call a phone
 * `smartphone`.
 *
 * @internal
 */
final class DeviceLabel
{
    public static function for(?string $type): string
    {
        $normalised = self::normalised($type);

        return match ($normalised) {
            'desktop' => __('Ordinateur'),
            'smartphone', 'mobile' => __('Mobile'),
            'feature phone' => __('Téléphone simple'),
            'phablet' => __('Phablette'),
            'tablet' => __('Tablette'),
            'tv' => __('Télévision'),
            'smart display' => __('Écran connecté'),
            'camera' => __('Appareil photo'),
            'smart speaker' => __('Enceinte connectée'),
            'console' => __('Console'),
            'car browser' => __('Voiture'),
            'portable media player' => __('Baladeur'),
            'wearable' => __('Objet connecté'),
            'peripheral' => __('Périphérique'),
            '' => __('Inconnu'),
            default => Str::title($normalised),
        };
    }

    /** The drawing of the device · a question mark when there is none for it. */
    public static function icon(?string $type): string
    {
        return match (self::normalised($type)) {
            'desktop' => 'computer-desktop',
            'smartphone', 'mobile', 'feature phone', 'phablet' => 'device-phone-mobile',
            'tablet' => 'device-tablet',
            'tv', 'smart display' => 'tv',
            'camera' => 'camera',
            'smart speaker' => 'speaker-wave',
            default => 'question-mark-circle',
        };
    }

    private static function normalised(?string $type): string
    {
        return $type !== null ? strtolower($type) : '';
    }
}
