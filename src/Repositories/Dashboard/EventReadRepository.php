<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Events\TrackedEvent;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Repositories\Concerns\ScopesSessionQueries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read model for the site-wide events and conversions screen: how many times each
 * named event fired, how many are conversions (key events), and the daily trend.
 * Bots are excluded, consistently with every other dashboard read.
 */
final class EventReadRepository
{
    use ScopesSessionQueries;

    /**
     * Per-event occurrence counts and unique visitors over the period, joined with
     * the declared registry (label, value, conversion). Sorted by count, desc.
     *
     * @return list<array{name: string, label: string, isConversion: bool, value: float|null, count: int, visitors: int, valueTotal: float}>
     */
    public function eventBreakdown(Period $period, ?string $subjectType, EventRegistry $events): array
    {
        $declared = [];
        foreach ($events->all() as $event) {
            $declared[$event->name] = $event;
        }

        $rows = $this->namedEvents($period, $subjectType)
            ->selectRaw('name, COUNT(*) as total, COUNT(DISTINCT visitor_id) as visitors')
            ->groupBy('name')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $name = (string) $row->getAttribute('name');
            $definition = $declared[$name] ?? null;
            $value = $definition instanceof TrackedEvent ? $definition->value : null;
            $count = (int) $row->getAttribute('total');

            $result[] = [
                'name' => $name,
                'label' => $definition instanceof TrackedEvent ? $definition->label : $name,
                'isConversion' => $definition instanceof TrackedEvent && $definition->isConversion(),
                'value' => $value,
                'count' => $count,
                'visitors' => (int) $row->getAttribute('visitors'),
                'valueTotal' => $value !== null ? $value * $count : 0.0,
            ];
        }

        usort($result, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $result;
    }

    /**
     * Headline totals over the period.
     *
     * @return array{events: int, conversions: int, value: float}
     */
    public function headline(Period $period, ?string $subjectType, EventRegistry $events): array
    {
        $eventsTotal = 0;
        $conversionsTotal = 0;
        $value = 0.0;

        foreach ($this->eventBreakdown($period, $subjectType, $events) as $row) {
            $eventsTotal += $row['count'];
            if ($row['isConversion']) {
                $conversionsTotal += $row['count'];
                $value += $row['valueTotal'];
            }
        }

        return ['events' => $eventsTotal, 'conversions' => $conversionsTotal, 'value' => $value];
    }

    /**
     * Daily event and conversion counts, each keyed by Y-m-d.
     *
     * @return array{events: array<string, int>, conversions: array<string, int>}
     */
    public function daily(Period $period, ?string $subjectType, EventRegistry $events): array
    {
        $conversionNames = [];
        foreach ($events->all() as $event) {
            if ($event->isConversion()) {
                $conversionNames[] = $event->name;
            }
        }

        $day = $this->dayExpression('occurred_at');

        $count = fn (Builder $query): array => $query
            ->selectRaw("{$day} as day, COUNT(*) as total")
            ->groupBy('day')
            ->get()
            ->mapWithKeys(fn (Model $row): array => [(string) $row->getAttribute('day') => (int) $row->getAttribute('total')])
            ->all();

        return [
            'events' => $count($this->namedEvents($period, $subjectType)),
            'conversions' => $conversionNames === [] ? [] : $count($this->namedEvents($period, $subjectType)->whereIn('name', $conversionNames)),
        ];
    }

    /**
     * @return Builder<Event>
     */
    private function namedEvents(Period $period, ?string $subjectType): Builder
    {
        return Event::query()
            ->whereNotNull('name')
            ->whereBetween('occurred_at', [$period->from, $period->to])
            ->whereHas('session', fn (Builder $session): Builder => $session->where('is_bot', false))
            ->when($subjectType !== null, fn (Builder $query): Builder => $query->whereHas(
                'visitor',
                fn (Builder $visitor): Builder => $visitor->where('subject_type', $subjectType),
            ));
    }
}
