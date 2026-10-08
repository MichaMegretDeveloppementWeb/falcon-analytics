<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Models\DailyEventTotal;
use Falcon\Analytics\Models\DailySessionTotal;
use Falcon\Analytics\Support\SubjectFilter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The daily totals of sessions and named events, read over the days of a
 * period · the stand-in for rows the purge has erased. Each reading answers in
 * the shape of the rows' reading it completes, so the two add up.
 *
 * @internal
 */
final class DailyTotalsReadRepository
{
    /**
     * @return array{sessions: int, pageviews: int, seconds: int, bounces: int}
     */
    public function engagement(Period $period, ?string $subjectType): array
    {
        $row = $this->sessions(DailySessionTotal::DIMENSION_ALL, $period, $subjectType)
            ->selectRaw('COALESCE(SUM(sessions), 0) as sessions, COALESCE(SUM(pageviews), 0) as pageviews, COALESCE(SUM(seconds), 0) as seconds, COALESCE(SUM(bounces), 0) as bounces')
            ->first();

        $columns = $row === null ? [] : (array) $row;

        return [
            'sessions' => (int) ($columns['sessions'] ?? 0),
            'pageviews' => (int) ($columns['pageviews'] ?? 0),
            'seconds' => (int) ($columns['seconds'] ?? 0),
            'bounces' => (int) ($columns['bounces'] ?? 0),
        ];
    }

    /**
     * @return array<string, array{sessions: int, pageviews: int, seconds: int, bounces: int}> by Y-m-d
     */
    public function engagementByDay(Period $period, ?string $subjectType): array
    {
        $days = [];

        $rows = $this->sessions(DailySessionTotal::DIMENSION_ALL, $period, $subjectType)
            ->selectRaw('day, SUM(sessions) as sessions, SUM(pageviews) as pageviews, SUM(seconds) as seconds, SUM(bounces) as bounces')
            ->groupBy('day')
            ->get();

        foreach ($rows as $row) {
            $columns = (array) $row;
            $days[(string) $columns['day']] = [
                'sessions' => (int) $columns['sessions'],
                'pageviews' => (int) $columns['pageviews'],
                'seconds' => (int) $columns['seconds'],
                'bounces' => (int) $columns['bounces'],
            ];
        }

        return $days;
    }

    /**
     * Sessions by the first key of a breakdown · a device, a country, a source.
     * Groups without a key are left out, as the rows' readings leave them.
     *
     * @return array<string, int>
     */
    public function sessionsByKey(string $dimension, Period $period, ?string $subjectType): array
    {
        return $this->sessions($dimension, $period, $subjectType)
            ->whereNotNull('key_a')
            ->selectRaw('key_a, SUM(sessions) as total')
            ->groupBy('key_a')
            ->pluck('total', 'key_a')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * Sessions by locality, keyed `country|city` as the overview keys them.
     *
     * @return array<string, int>
     */
    public function sessionsByLocality(Period $period, ?string $subjectType): array
    {
        $localities = [];

        $rows = $this->sessions(DailySessionTotal::DIMENSION_LOCALITY, $period, $subjectType)
            ->whereNotNull('key_a')
            ->selectRaw('key_a, key_b, SUM(sessions) as total')
            ->groupBy('key_a', 'key_b')
            ->get();

        foreach ($rows as $row) {
            $columns = (array) $row;
            $localities[$columns['key_a'].'|'.($columns['key_b'] ?? '')] = (int) $columns['total'];
        }

        return $localities;
    }

    /**
     * The sessions that arrived with parameters, by source, day and
     * parameters · what a campaign claims when a screen is read.
     *
     * @return list<array{source: string|null, day: string, params: array<string, string>, sessions: int}>
     */
    public function sessionsWithParameters(Period $period, ?string $subjectType): array
    {
        $groups = [];

        $rows = $this->sessions(DailySessionTotal::DIMENSION_SOURCE, $period, $subjectType)
            ->whereNotNull('mkt_params')
            ->selectRaw('day, key_a, mkt_params, sessions')
            ->get();

        foreach ($rows as $row) {
            $columns = (array) $row;
            $params = json_decode((string) $columns['mkt_params'], true);

            $groups[] = [
                'source' => is_scalar($columns['key_a']) ? (string) $columns['key_a'] : null,
                'day' => (string) $columns['day'],
                'params' => is_array($params) ? array_map('strval', $params) : [],
                'sessions' => (int) $columns['sessions'],
            ];
        }

        return $groups;
    }

    /**
     * Named events by name · how many, the scores they carried and how many
     * carried one.
     *
     * @return array<string, array{total: int, carried: int, scored: int}>
     */
    public function events(Period $period, ?string $subjectType): array
    {
        $events = [];

        $rows = $this->namedEvents($period, $subjectType)
            ->selectRaw('name, SUM(total) as total, SUM(value_sum) as carried, SUM(value_count) as scored')
            ->groupBy('name')
            ->get();

        foreach ($rows as $row) {
            $columns = (array) $row;
            $events[(string) $columns['name']] = [
                'total' => (int) $columns['total'],
                'carried' => (int) $columns['carried'],
                'scored' => (int) $columns['scored'],
            ];
        }

        return $events;
    }

    /**
     * Named events by day, all of them or those of some names.
     *
     * @param  list<string>|null  $names
     * @return array<string, int> by Y-m-d
     */
    public function eventsByDay(Period $period, ?string $subjectType, ?array $names = null): array
    {
        return $this->namedEvents($period, $subjectType)
            ->when($names !== null, fn (Builder $query): Builder => $query->whereIn('name', $names ?? []))
            ->selectRaw('day, SUM(total) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->mapWithKeys(fn (mixed $total, mixed $day): array => [(string) $day => (int) $total])
            ->all();
    }

    private function sessions(string $dimension, Period $period, ?string $subjectType): Builder
    {
        return SubjectFilter::apply(
            DB::table(DailySessionTotal::TABLE)
                ->where('dimension', $dimension)
                ->whereBetween('day', [$period->from->toDateString(), $period->to->toDateString()]),
            $subjectType,
        );
    }

    private function namedEvents(Period $period, ?string $subjectType): Builder
    {
        return SubjectFilter::apply(
            DB::table(DailyEventTotal::TABLE)
                ->whereBetween('day', [$period->from->toDateString(), $period->to->toDateString()]),
            $subjectType,
        );
    }
}
