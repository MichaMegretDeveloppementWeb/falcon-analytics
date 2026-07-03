<?php

use Falcon\Analytics\Support\DeviceLabel;

it('maps known device types to their translated label, case-insensitively', function () {
    expect(DeviceLabel::for('desktop'))->toBe('Ordinateur')
        ->and(DeviceLabel::for('MOBILE'))->toBe('Mobile')
        ->and(DeviceLabel::for('tablet'))->toBe('Tablette');
});

it('falls back to a title-cased label, or "Inconnu" for empty/null', function () {
    expect(DeviceLabel::for('console'))->toBe('Console')
        ->and(DeviceLabel::for(''))->toBe('Inconnu')
        ->and(DeviceLabel::for(null))->toBe('Inconnu');
});
