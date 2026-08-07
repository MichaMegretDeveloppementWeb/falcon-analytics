<?php

declare(strict_types=1);

namespace Falcon\Analytics\Funnels;

use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;

/**
 * One alternative route through a funnel step: a labelled way of satisfying the
 * same milestone. Branches sit side by side at the same depth, so a visitor
 * reaching the step through any of them advances exactly once.
 */
final readonly class FunnelBranch
{
    private function __construct(
        public string $label,
        public ?string $event = null,
        public ?string $route = null,
    ) {}

    /** A branch satisfied by a named event. */
    public static function event(string $label, string $event): self
    {
        return new self($label, event: $event);
    }

    /** A branch satisfied by a pageview on the given route. */
    public static function route(string $label, string $route): self
    {
        return new self($label, route: $route);
    }

    public function matches(Event $event): bool
    {
        if ($this->event !== null) {
            return $event->name === $this->event;
        }

        return $event->type === EventType::Pageview && $event->route === $this->route;
    }
}
