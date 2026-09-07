<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Support\DeviceLabel;
use Falcon\Analytics\Tests\TestCase;

final class DeviceLabelTest extends TestCase
{
    public function test_it_maps_known_device_types_to_their_translated_label_case_insensitively(): void
    {
        $this->assertSame('Ordinateur', DeviceLabel::for('desktop'));
        $this->assertSame('Mobile', DeviceLabel::for('MOBILE'));
        $this->assertSame('Tablette', DeviceLabel::for('tablet'));
    }

    public function test_it_falls_back_to_a_title_cased_label_or_inconnu_for_empty_and_null(): void
    {
        $this->assertSame('Console', DeviceLabel::for('console'));
        $this->assertSame('Inconnu', DeviceLabel::for(''));
        $this->assertSame('Inconnu', DeviceLabel::for(null));
    }
}
