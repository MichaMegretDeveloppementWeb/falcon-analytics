<?php

declare(strict_types=1);

namespace Falcon\Analytics\DTOs\Dashboard\Visitor;

/**
 * The sessions a visitor opened on one kind of device.
 *
 * @internal
 */
final readonly class DeviceShare
{
    /**
     * @param  int  $percent  of the visitor's sessions, rounded
     * @param  string  $color  the series token that draws it
     */
    public function __construct(
        public string $label,
        public int $sessions,
        public int $percent,
        public string $color,
    ) {}
}
