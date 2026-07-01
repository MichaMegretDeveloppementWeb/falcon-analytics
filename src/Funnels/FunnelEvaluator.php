<?php

declare(strict_types=1);

namespace Falcon\Analytics\Funnels;

use Falcon\Analytics\DTOs\Dashboard\FunnelReport;
use Falcon\Analytics\DTOs\Dashboard\FunnelStepResult;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;
use Illuminate\Database\Eloquent\Builder;

/**
 * Evaluates code-declared funnels against the raw events. Progression is
 * sequential: a visitor reaches step i only by matching each earlier step in
 * chronological order first, so reach is monotonically decreasing and a step
 * can never out-count the one before it.
 */
final readonly class FunnelEvaluator
{
    public function __construct(private FunnelRegistry $registry) {}

    /**
     * @return list<FunnelReport>
     */
    public function evaluateAll(Period $period, ?string $subjectType): array
    {
        return array_map(
            fn (Funnel $funnel): FunnelReport => $this->evaluate($funnel, $period, $subjectType),
            $this->registry->all(),
        );
    }

    public function evaluate(Funnel $funnel, Period $period, ?string $subjectType): FunnelReport
    {
        $steps = $funnel->steps();
        $stepCount = count($steps);
        $reached = array_fill(0, max($stepCount, 1), 0);

        foreach ($this->journeys($funnel, $period, $subjectType) as $events) {
            $pointer = 0;

            foreach ($events as $event) {
                if ($pointer >= $stepCount) {
                    break;
                }

                if ($this->matches($steps[$pointer], $event)) {
                    $pointer++;
                }
            }

            for ($i = 0; $i < $pointer; $i++) {
                $reached[$i]++;
            }
        }

        $entrants = $stepCount > 0 ? $reached[0] : 0;
        $results = [];
        $totalScore = 0.0;

        foreach ($steps as $i => $step) {
            $visitors = $reached[$i];
            $score = $visitors * $step->value;
            $totalScore += $score;

            $results[] = new FunnelStepResult(
                label: $step->label,
                value: $step->value,
                visitors: $visitors,
                conversionFromStart: $entrants > 0 ? $visitors / $entrants : 0.0,
                conversionFromPrevious: $i === 0 ? 1.0 : ($reached[$i - 1] > 0 ? $visitors / $reached[$i - 1] : 0.0),
                score: $score,
            );
        }

        return new FunnelReport($funnel->key, $funnel->label, $entrants, $totalScore, $results);
    }

    /**
     * Relevant events grouped by visitor, in chronological order. Only events
     * that can match a step are loaded. The subject filter is applied on the
     * visitor's identity, not the event, so an anonymous first step still counts
     * for a visitor who later signed in.
     *
     * @return iterable<int, list<Event>>
     */
    private function journeys(Funnel $funnel, Period $period, ?string $subjectType): iterable
    {
        $names = [];
        $routes = [];

        foreach ($funnel->steps() as $step) {
            if ($step->event !== null) {
                $names[] = $step->event;
            }

            if ($step->route !== null) {
                $routes[] = $step->route;
            }
        }

        if ($names === [] && $routes === []) {
            return [];
        }

        $query = Event::query()
            ->select(['id', 'visitor_id', 'type', 'name', 'route', 'occurred_at'])
            ->whereBetween('occurred_at', [$period->from, $period->to])
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

        $grouped = [];

        foreach ($query->cursor() as $event) {
            $grouped[$event->visitor_id][] = $event;
        }

        return $grouped;
    }

    private function matches(FunnelStep $step, Event $event): bool
    {
        if ($step->event !== null) {
            return $event->name === $step->event;
        }

        return $event->type === EventType::Pageview && $event->route === $step->route;
    }
}
