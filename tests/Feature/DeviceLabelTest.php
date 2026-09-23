<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Support\DeviceLabel;
use Falcon\Analytics\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The device a session was opened on, named and drawn · from the names the
 * user-agent library records, which call a phone `smartphone` and not
 * `mobile`.
 */
final class DeviceLabelTest extends TestCase
{
    /** @return array<string, array{string|null, string, string}> */
    public static function devices(): array
    {
        return [
            'a computer' => ['desktop', 'Ordinateur', 'computer-desktop'],
            'a phone, as recorded' => ['smartphone', 'Mobile', 'device-phone-mobile'],
            'a phone, by its short name' => ['mobile', 'Mobile', 'device-phone-mobile'],
            'a phone without a touch screen' => ['feature phone', 'Téléphone simple', 'device-phone-mobile'],
            'a large phone' => ['phablet', 'Phablette', 'device-phone-mobile'],
            'a tablet' => ['tablet', 'Tablette', 'device-tablet'],
            'a television' => ['tv', 'Télévision', 'tv'],
            'a smart display' => ['smart display', 'Écran connecté', 'tv'],
            'a camera' => ['camera', 'Appareil photo', 'camera'],
            'a smart speaker' => ['smart speaker', 'Enceinte connectée', 'speaker-wave'],
            'a console' => ['console', 'Console', 'question-mark-circle'],
            'a car' => ['car browser', 'Voiture', 'question-mark-circle'],
            'a media player' => ['portable media player', 'Baladeur', 'question-mark-circle'],
            'a wearable' => ['wearable', 'Objet connecté', 'question-mark-circle'],
            'a peripheral' => ['peripheral', 'Périphérique', 'question-mark-circle'],
            'whatever the case' => ['SmartPhone', 'Mobile', 'device-phone-mobile'],
            'nothing recorded' => ['', 'Inconnu', 'question-mark-circle'],
            'no value' => [null, 'Inconnu', 'question-mark-circle'],
        ];
    }

    #[DataProvider('devices')]
    public function test_a_device_is_named_and_drawn(?string $type, string $label, string $icon): void
    {
        $this->assertSame($label, DeviceLabel::for($type));
        $this->assertSame($icon, DeviceLabel::icon($type));
    }

    public function test_a_name_not_yet_known_reads_as_recorded(): void
    {
        $this->assertSame('Hologram', DeviceLabel::for('hologram'));
        $this->assertSame('question-mark-circle', DeviceLabel::icon('hologram'));
    }
}
