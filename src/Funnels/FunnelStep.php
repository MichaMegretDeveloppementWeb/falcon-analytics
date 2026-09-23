<?php

declare(strict_types=1);

namespace Falcon\Analytics\Funnels;

use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;

/**
 * One step of a funnel: a weighted milestone matched either by a named event,
 * by a pageview route, or by any of several parallel branches. The value is
 * the step's weight, **a whole number of points** like every score here.
 *
 * @internal a step is built by `Funnel::step()` and never by hand · the fluent
 *           call hands back the funnel, not the step.
 */
final readonly class FunnelStep
{
    /** @param  list<FunnelBranch>  $branches */
    public function __construct(
        public string $label,
        public int $value,
        public ?string $event = null,
        public ?string $route = null,
        public array $branches = [],
    ) {}

    /**
     * Whether the given event satisfies this step: a named-event step matches by
     * name, a route step matches a pageview on that route, and a branched step
     * matches as soon as one of its branches does. Single source of truth for
     * step matching, used by both the funnels screen and marketing conversions.
     */
    public function matches(Event $event): bool
    {
        if ($this->branches !== []) {
            return $this->branchFor($event) !== null;
        }

        if ($this->event !== null) {
            return $event->name === $this->event;
        }

        return $event->type === EventType::Pageview && $event->route === $this->route;
    }

    /**
     * The branch that satisfied this step, so a report can tell which way in
     * visitors came. Null for a step without branches.
     */
    public function branchFor(Event $event): ?FunnelBranch
    {
        foreach ($this->branches as $branch) {
            if ($branch->matches($event)) {
                return $branch;
            }
        }

        return null;
    }

    /**
     * Every event name that can satisfy this step, branches included, so the
     * walker loads them in one query.
     *
     * @return list<string>
     */
    public function eventNames(): array
    {
        if ($this->branches === []) {
            return $this->event !== null ? [$this->event] : [];
        }

        // Not a bare array_filter: it would also drop "" and "0", which the simple case keeps.
        return array_values(array_filter(
            array_map(
                static fn (FunnelBranch $branch): ?string => $branch->event,
                $this->branches,
            ),
            static fn (?string $name): bool => $name !== null,
        ));
    }

    /**
     * Every pageview route that can satisfy this step, branches included.
     *
     * @return list<string>
     */
    public function routeNames(): array
    {
        if ($this->branches === []) {
            return $this->route !== null ? [$this->route] : [];
        }

        return array_values(array_filter(
            array_map(
                static fn (FunnelBranch $branch): ?string => $branch->route,
                $this->branches,
            ),
            static fn (?string $route): bool => $route !== null,
        ));
    }
}
