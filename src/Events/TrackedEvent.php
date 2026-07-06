<?php

declare(strict_types=1);

namespace Falcon\Analytics\Events;

/**
 * A tracked event declared in code: its technical name (as recorded), a human
 * label, and an optional default value. The declared set is the single source of
 * truth for the events offered as conversion objectives.
 */
final class TrackedEvent
{
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly ?float $value = null,
    ) {}

    /**
     * Declare a tracked event and register it with the loading registry.
     */
    public static function define(string $name, string $label, ?float $value = null): self
    {
        $event = new self($name, $label, $value);
        EventRegistry::current()?->register($event);

        return $event;
    }
}
