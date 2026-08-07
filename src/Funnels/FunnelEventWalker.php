<?php

declare(strict_types=1);

namespace Falcon\Analytics\Funnels;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;
use Illuminate\Database\Eloquent\Builder;

/**
 * The single funnel-walking machinery shared by the funnels screen and the
 * marketing conversions: it loads only the events that can match a step
 * (named events or pageview routes), excludes bot sessions, optionally narrows
 * to a visitor set and a subject identity, then streams the events in
 * (visitor, chronological) order and advances a per-visitor step pointer.
 * Progression is strictly sequential: a visitor reaches step i only by
 * matching each earlier step in order first. Consumers observe the walk
 * through a callback fired on every advance, so completers, per-step reach
 * and full reports all derive from the same semantics without ever
 * accumulating the event models in memory.
 */
final readonly class FunnelEventWalker
{
    /**
     * Walk the funnel over the period and invoke the callback each time a
     * visitor advances by one step. The callback receives the visitor id, the
     * 0-based index of the step just reached and the matching event; a visitor
     * whose pointer passed the last step can no longer advance, so the last
     * index fires at most once per visitor.
     *
     * @param  list<int>|null  $visitorIds  restrict the walk to these visitors; null walks everyone
     * @param  callable(int $visitorId, int $stepIndex, Event $event): void  $onStepReached
     */
    public function walk(Funnel $funnel, Period $period, ?string $subjectType, ?array $visitorIds, callable $onStepReached): void
    {
        $steps = $funnel->steps();
        $stepCount = count($steps);

        if ($stepCount === 0 || $visitorIds === []) {
            return;
        }

        $names = [];
        $routes = [];
        foreach ($steps as $step) {
            $names = [...$names, ...$step->eventNames()];
            $routes = [...$routes, ...$step->routeNames()];
        }

        if ($names === [] && $routes === []) {
            return;
        }

        /** @var array<int, int> $pointer next step index awaited per visitor */
        $pointer = [];

        foreach ($this->eventQuery($period, $subjectType, $visitorIds, $names, $routes)->cursor() as $event) {
            $visitorId = (int) $event->visitor_id;
            $position = $pointer[$visitorId] ?? 0;

            if ($position < $stepCount && $steps[$position]->matches($event)) {
                $pointer[$visitorId] = $position + 1;
                $onStepReached($visitorId, $position, $event);
            }
        }
    }

    /**
     * The events that can match a funnel step, bot sessions excluded, ordered
     * for the sequential walk. The subject filter is applied on the visitor's
     * identity, not the event, so an anonymous first step still counts for a
     * visitor who later signed in.
     *
     * @param  list<int>|null  $visitorIds
     * @param  list<string>  $names
     * @param  list<string>  $routes
     * @return Builder<Event>
     */
    private function eventQuery(Period $period, ?string $subjectType, ?array $visitorIds, array $names, array $routes): Builder
    {
        return Event::query()
            ->select(['id', 'visitor_id', 'type', 'name', 'route', 'occurred_at'])
            ->when($visitorIds !== null, fn (Builder $query): Builder => $query->whereIn('visitor_id', $visitorIds))
            ->whereBetween('occurred_at', [$period->from, $period->to])
            ->whereHas('session', fn (Builder $session): Builder => $session->where('is_bot', false))
            ->where(function (Builder $matcher) use ($names, $routes): void {
                if ($names !== []) {
                    $matcher->whereIn('name', $names);
                }
                if ($routes !== []) {
                    $matcher->orWhere(function (Builder $inner) use ($routes): void {
                        $inner->where('type', EventType::Pageview)->whereIn('route', $routes);
                    });
                }
            })
            ->when($subjectType !== null, fn (Builder $query): Builder => $query->whereHas(
                'visitor',
                fn (Builder $visitor): Builder => $visitor->where('subject_type', $subjectType),
            ))
            ->orderBy('visitor_id')
            ->orderBy('occurred_at')
            ->orderBy('id');
    }
}
