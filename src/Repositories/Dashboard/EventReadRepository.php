<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Events\TrackedEvent;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Repositories\Concerns\ScopesSessionQueries;
use Falcon\Analytics\Services\RetentionWindow;
use Falcon\Analytics\Support\SubjectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read model for the site-wide events and conversions screen: how many times each
 * named event fired, how many are conversions (key events), and the daily trend.
 * Bots are excluded, consistently with every other dashboard read.
 *
 * @internal
 */
final class EventReadRepository
{
    use ScopesSessionQueries;

    public function __construct(
        private readonly RetentionWindow $window = new RetentionWindow,
        private readonly DailyTotalsReadRepository $totals = new DailyTotalsReadRepository,
    ) {}

    /**
     * Per-event occurrence counts and unique visitors over the period, joined with
     * the declared registry (label, value, conversion). Sorted by count, desc.
     *
     * The score of an occurrence is the one it carries, and the declared one
     * for the occurrences that carry none: both are added up in the same read.
     *
     * Days whose named events are erased read from their totals · the counts
     * and scores add up, the distinct visitors do not and are left out.
     *
     * @return list<array{name: string, label: string, isConversion: bool, value: int|null, count: int, visitors: int|null, valueTotal: int, isScored: bool}>
     */
    public function eventBreakdown(Period $period, ?string $subjectType, EventRegistry $events): array
    {
        $declared = [];
        foreach ($events->all() as $event) {
            $declared[$event->name] = $event;
        }

        $result = [];
        foreach ($this->namedEventCounts($period, $subjectType) as $name => $counts) {
            $definition = $declared[$name] ?? null;
            $value = $definition instanceof TrackedEvent ? $definition->value : null;

            $result[] = [
                'name' => $name,
                'label' => $definition instanceof TrackedEvent ? $definition->label : $name,
                'isConversion' => $definition instanceof TrackedEvent && $definition->isConversion(),
                'value' => $value,
                'count' => $counts['total'],
                'visitors' => $counts['visitors'],
                'valueTotal' => $counts['carried'] + ($value ?? 0) * ($counts['total'] - $counts['scored']),
                'isScored' => $value !== null || $counts['scored'] > 0,
            ];
        }

        usort($result, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $result;
    }

    /**
     * Headline totals over the period.
     *
     * @return array{events: int, conversions: int, value: int}
     */
    public function headline(Period $period, ?string $subjectType, EventRegistry $events): array
    {
        return $this->totals($this->eventBreakdown($period, $subjectType, $events));
    }

    /**
     * Headline totals derived from an already-computed breakdown, so a caller that
     * also needs the breakdown does not pay for a second aggregation query.
     *
     * @param  list<array{name: string, label: string, isConversion: bool, value: int|null, count: int, visitors: int|null, valueTotal: int, isScored: bool}>  $breakdown
     * @return array{events: int, conversions: int, value: int}
     */
    public function totals(array $breakdown): array
    {
        $eventsTotal = 0;
        $conversionsTotal = 0;
        $value = 0;

        foreach ($breakdown as $row) {
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

        ['totals' => $summarised, 'rows' => $detailed] = RetentionWindow::split($period, $this->window->eventsLine());
        $day = $this->dayExpression('occurred_at');

        $count = fn (Builder $query): array => $query
            ->selectRaw("{$day} as day, COUNT(*) as total")
            ->groupBy('day')
            ->get()
            ->mapWithKeys(fn (Model $row): array => [(string) $row->getAttribute('day') => (int) $row->getAttribute('total')])
            ->all();

        $events = $summarised === null ? [] : $this->totals->eventsByDay($summarised, $subjectType);
        $conversions = $summarised === null || $conversionNames === [] ? [] : $this->totals->eventsByDay($summarised, $subjectType, $conversionNames);

        if ($detailed === null) {
            return ['events' => $events, 'conversions' => $conversions];
        }

        return [
            'events' => [...$events, ...$count($this->namedEvents($detailed, $subjectType))],
            'conversions' => $conversionNames === [] ? [] : [...$conversions, ...$count($this->namedEvents($detailed, $subjectType)->whereIn('name', $conversionNames))],
        ];
    }

    /**
     * Each named event's occurrences, scores and distinct visitors over the
     * period · the visitors null when it reaches erased days.
     *
     * @return array<string, array{total: int, visitors: int|null, carried: int, scored: int}>
     */
    private function namedEventCounts(Period $period, ?string $subjectType): array
    {
        ['totals' => $summarised, 'rows' => $detailed] = RetentionWindow::split($period, $this->window->eventsLine());

        $counts = [];

        foreach ($summarised === null ? [] : $this->totals->events($summarised, $subjectType) as $name => $added) {
            $counts[$name] = [...$added, 'visitors' => null];
        }

        $rows = $detailed === null ? [] : $this->namedEvents($detailed, $subjectType)
            ->selectRaw('name, COUNT(*) as total, COUNT(DISTINCT visitor_id) as visitors, SUM(value) as carried, COUNT(value) as scored')
            ->groupBy('name')
            ->get();

        foreach ($rows as $row) {
            $name = (string) $row->getAttribute('name');
            $before = $counts[$name] ?? null;

            $counts[$name] = [
                'total' => (int) $row->getAttribute('total') + ($before['total'] ?? 0),
                'visitors' => $summarised === null ? (int) $row->getAttribute('visitors') : null,
                'carried' => (int) $row->getAttribute('carried') + ($before['carried'] ?? 0),
                'scored' => (int) $row->getAttribute('scored') + ($before['scored'] ?? 0),
            ];
        }

        return $counts;
    }

    /**
     * @return Builder<Event>
     */
    private function namedEvents(Period $period, ?string $subjectType): Builder
    {
        return Event::query()
            ->where('name', '<>', '')
            ->whereBetween('occurred_at', [$period->from, $period->to])
            ->whereHas('session', fn (Builder $session): Builder => $session->where('is_bot', false))
            ->when($subjectType !== null, fn (Builder $query): Builder => $query->whereHas(
                'visitor',
                fn (Builder $visitor): Builder => SubjectFilter::apply($visitor, $subjectType),
            ));
    }
}
