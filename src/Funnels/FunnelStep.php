<?php

declare(strict_types=1);

namespace Falcon\Analytics\Funnels;

use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;

/**
 * One step of a funnel: a weighted milestone matched either by a named event
 * (a data-track-event) or by a pageview route. The value is the step's weight.
 */
final readonly class FunnelStep
{
    public function __construct(
        public string $label,
        public float $value,
        public ?string $event = null,
        public ?string $route = null,
    ) {}

    /**
     * Whether the given event satisfies this step: a named-event step matches by
     * name, a route step matches a pageview on that route. Single source of truth
     * for step matching, used by both the funnels screen and marketing conversions.
     */
    public function matches(Event $event): bool
    {
        if ($this->event !== null) {
            return $event->name === $this->event;
        }

        return $event->type === EventType::Pageview && $event->route === $this->route;
    }
}
