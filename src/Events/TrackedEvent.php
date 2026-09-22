<?php

declare(strict_types=1);

namespace Falcon\Analytics\Events;

/**
 * A tracked event declared in code: its technical name (as recorded), a human
 * label, an optional score, and whether it counts as a conversion (a key
 * event). The declared set is the single source of truth for the events offered as
 * conversion objectives and for the analytics events/conversions screen.
 *
 * **The score is a whole number of points, never an amount**: the screens add
 * it up and show it in points.
 */
final class TrackedEvent
{
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly ?int $value = null,
        private readonly ?bool $conversion = null,
    ) {}

    /**
     * Declare a tracked event and register it with the loading registry. Pass
     * conversion: true/false to force whether it is a key event; by default any
     * event that carries a score is treated as a conversion.
     */
    public static function define(string $name, string $label, ?int $value = null, ?bool $conversion = null): self
    {
        $event = new self($name, $label, $value, $conversion);
        EventRegistry::current()?->register($event);

        return $event;
    }

    /**
     * Whether this event is a conversion (a key event). Defaults to any event that
     * carries a score; can be forced on or off explicitly.
     */
    public function isConversion(): bool
    {
        return $this->conversion ?? ($this->value !== null);
    }
}
