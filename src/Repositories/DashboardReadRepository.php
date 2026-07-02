<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\MetricDelta;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\DTOs\Dashboard\TrendPoint;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Services\SubjectResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read model for the dashboard pages. Session-based figures exclude bots and are
 * scoped to a Period and optional subject type; headline and engagement figures
 * carry their previous-period value for comparison.
 */
final readonly class DashboardReadRepository
{
    /**
     * Headline and engagement metrics, each with its previous-period value.
     *
     * @return array{
     *     visitors: MetricDelta, sessions: MetricDelta, pageviews: MetricDelta,
     *     avgSeconds: MetricDelta, pagesPerSession: MetricDelta, bounceRate: MetricDelta
     * }
     */
    public function headline(Period $period, ?string $subjectType): array
    {
        $current = $this->aggregates($period, $subjectType);
        $previous = $this->aggregates($period->previous(), $subjectType);

        $pagesPerSession = fn (object $a): float => $a->sessions > 0 ? (float) $a->pageviews / $a->sessions : 0.0;
        $bounceRate = fn (object $a): float => $a->sessions > 0 ? (float) $a->bounces / $a->sessions * 100 : 0.0;

        return [
            'visitors' => new MetricDelta((float) $current->visitors, (float) $previous->visitors),
            'sessions' => new MetricDelta((float) $current->sessions, (float) $previous->sessions),
            'pageviews' => new MetricDelta((float) $current->pageviews, (float) $previous->pageviews),
            'avgSeconds' => new MetricDelta((float) $current->avg_seconds, (float) $previous->avg_seconds),
            'pagesPerSession' => new MetricDelta($pagesPerSession($current), $pagesPerSession($previous)),
            'bounceRate' => new MetricDelta($bounceRate($current), $bounceRate($previous)),
        ];
    }

    /**
     * Today and yesterday values for the headline metrics, feeding the
     * "X today, Y yesterday" mini-line. Duration uses last_activity (never now),
     * so open sessions can't inflate it.
     *
     * @return array<string, array{today: float, yesterday: float}>
     */
    public function spotlight(?string $subjectType): array
    {
        $now = CarbonImmutable::now();
        $today = $this->aggregates(new Period($now->startOfDay(), $now, 1), $subjectType);
        $yesterday = $this->aggregates(new Period($now->subDay()->startOfDay(), $now->subDay()->endOfDay(), 1), $subjectType);

        $bounce = fn (object $row): float => $row->sessions > 0 ? (float) $row->bounces / $row->sessions * 100 : 0.0;

        return [
            'visitors' => ['today' => (float) $today->visitors, 'yesterday' => (float) $yesterday->visitors],
            'sessions' => ['today' => (float) $today->sessions, 'yesterday' => (float) $yesterday->sessions],
            'avgSeconds' => ['today' => (float) $today->avg_seconds, 'yesterday' => (float) $yesterday->avg_seconds],
            'bounceRate' => ['today' => $bounce($today), 'yesterday' => $bounce($yesterday)],
        ];
    }

    /**
     * New (first ever seen within the period) vs returning visitor counts.
     *
     * @return array{new: int, returning: int}
     */
    public function newVsReturning(Period $period, ?string $subjectType): array
    {
        $total = $this->sessionScope($period, $subjectType)->distinct()->count('visitor_id');

        $new = $this->sessionScope($period, $subjectType)
            ->whereHas('visitor', fn (Builder $visitor): Builder => $visitor->whereBetween('first_seen_at', [$period->from, $period->to]))
            ->distinct()
            ->count('visitor_id');

        return ['new' => $new, 'returning' => max($total - $new, 0)];
    }

    /**
     * Session counts per device type (desktop / mobile / tablet).
     *
     * @return array<string, int>
     */
    public function sessionsByDevice(Period $period, ?string $subjectType): array
    {
        return $this->sessionScope($period, $subjectType)
            ->toBase()
            ->whereNotNull('device_type')
            ->selectRaw('device_type, COUNT(*) as total')
            ->groupBy('device_type')
            ->orderByDesc('total')
            ->pluck('total', 'device_type')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    /**
     * Share of new visitors among the period's visitors, with its
     * previous-period value.
     */
    public function newVisitorRate(Period $period, ?string $subjectType): MetricDelta
    {
        $rate = function (Period $window) use ($subjectType): float {
            $counts = $this->newVsReturning($window, $subjectType);
            $total = $counts['new'] + $counts['returning'];

            return $total > 0 ? $counts['new'] / $total * 100 : 0.0;
        };

        return new MetricDelta($rate($period), $rate($period->previous()));
    }

    /**
     * Daily sessions and page views across the period, with missing days filled
     * so the chart draws a continuous line.
     *
     * @return list<TrendPoint>
     */
    public function dailyTrend(Period $period, ?string $subjectType): array
    {
        $day = $this->dayExpression('started_at');

        $rows = $this->sessionScope($period, $subjectType)
            ->toBase()
            ->selectRaw("{$day} as day, COUNT(*) as session_total, COALESCE(SUM(pageview_count), 0) as pageview_total")
            ->groupBy(DB::raw($day))
            ->get()
            ->keyBy('day');

        $points = [];

        for ($cursor = $period->from->startOfDay(); $cursor->lessThanOrEqualTo($period->to); $cursor = $cursor->addDay()) {
            $row = $rows->get($cursor->format('Y-m-d'));

            $points[] = new TrendPoint(
                date: $cursor,
                sessions: (int) ($row->session_total ?? 0),
                pageviews: (int) ($row->pageview_total ?? 0),
            );
        }

        return $points;
    }

    /**
     * Daily zero-filled series for each headline metric, so the KPI tiles can
     * draw a mini trend (sparkline).
     *
     * @return array{visitors: list<float>, sessions: list<float>, avgSeconds: list<float>, bounceRate: list<float>, pagesPerSession: list<float>}
     */
    public function headlineSparklines(Period $period, ?string $subjectType): array
    {
        $day = $this->dayExpression('started_at');
        $duration = $this->durationSecondsExpression('started_at', 'last_activity_at');

        $rows = $this->sessionScope($period, $subjectType)
            ->toBase()
            ->selectRaw("{$day} as day, COUNT(*) as sessions, COUNT(DISTINCT visitor_id) as visitors, COALESCE(SUM(pageview_count), 0) as pageviews, COALESCE(AVG({$duration}), 0) as avg_seconds, SUM(CASE WHEN pageview_count <= 1 THEN 1 ELSE 0 END) as bounces")
            ->groupBy(DB::raw($day))
            ->get()
            ->keyBy('day');

        $series = ['visitors' => [], 'sessions' => [], 'avgSeconds' => [], 'bounceRate' => [], 'pagesPerSession' => []];

        for ($cursor = $period->from->startOfDay(); $cursor->lessThanOrEqualTo($period->to); $cursor = $cursor->addDay()) {
            $row = $rows->get($cursor->format('Y-m-d'));
            $sessions = (int) ($row->sessions ?? 0);

            $series['visitors'][] = (float) ($row->visitors ?? 0);
            $series['sessions'][] = (float) $sessions;
            $series['avgSeconds'][] = (float) ($row->avg_seconds ?? 0);
            $series['bounceRate'][] = $sessions > 0 ? (float) $row->bounces / $sessions * 100 : 0.0;
            $series['pagesPerSession'][] = $sessions > 0 ? (int) ($row->pageviews ?? 0) / $sessions : 0.0;
        }

        return $series;
    }

    /**
     * Top acquisition sources with their previous-period counts.
     *
     * @return list<array{label: string, total: int, previous: int}>
     */
    public function topSources(Period $period, ?string $subjectType, int $limit = 6): array
    {
        return $this->rankedSessionColumn('source', $period, $subjectType, $limit);
    }

    /**
     * Top localities (country + city) by sessions, with previous-period counts.
     *
     * @return list<array{country: string, city: string|null, total: int, previous: int}>
     */
    public function topLocalities(Period $period, ?string $subjectType, int $limit = 6): array
    {
        $counts = fn (Period $window): Collection => $this->sessionScope($window, $subjectType)
            ->toBase()
            ->whereNotNull('country')
            ->selectRaw('country, city, COUNT(*) as total')
            ->groupBy('country', 'city')
            ->get()
            ->keyBy(fn (object $row): string => $row->country.'|'.($row->city ?? ''));

        $previous = $counts($period->previous());

        return $counts($period)
            ->sortByDesc(fn (object $row): int => (int) $row->total)
            ->take($limit)
            ->map(fn (object $row): array => [
                'country' => (string) $row->country,
                'city' => ($row->city !== null && $row->city !== '') ? (string) $row->city : null,
                'total' => (int) $row->total,
                'previous' => (int) ($previous[$row->country.'|'.($row->city ?? '')]->total ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Most viewed pages (pageview events grouped by route), with the
     * previous-period count for each.
     *
     * @return list<array{label: string, total: int, previous: int}>
     */
    public function topPages(Period $period, ?string $subjectType, int $limit = 6): array
    {
        $current = $this->rankedEventCounts(EventType::Pageview, 'route', $period, $subjectType);
        $previous = $this->rankedEventCounts(EventType::Pageview, 'route', $period->previous(), $subjectType);

        return $this->mergeRanked($current, $previous, $limit);
    }

    /**
     * Most clicked elements (click events), each as a label + the page it sits
     * on + its count.
     *
     * @return list<array{label: string, route: ?string, total: int}>
     */
    public function topClicks(Period $period, ?string $subjectType, int $limit = 6): array
    {
        // Prefer the visible button text (human-readable) over the technical event name.
        $label = "COALESCE(NULLIF(target_text, ''), NULLIF(name, ''))";

        return $this->eventScope(EventType::Click, $period, $subjectType)
            ->whereRaw("{$label} IS NOT NULL")
            ->selectRaw("{$label} as label, route, COUNT(*) as total")
            ->groupBy(DB::raw($label), 'route')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'label' => (string) $row->label,
                'route' => $row->route !== null ? (string) $row->route : null,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * Paginated session list, newest first, optionally filtered by a free-text
     * term (IP / locality), device type and source.
     *
     * @return LengthAwarePaginator<int, Session>
     */
    public function paginateSessions(
        Period $period,
        ?string $subjectType,
        ?string $search,
        ?string $device,
        ?string $source,
        SubjectResolver $subjects,
        string $sort = 'started_at',
        string $direction = 'desc',
        int $perPage = 20,
    ): LengthAwarePaginator {
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        $query = $this->sessionScope($period, $subjectType)
            ->with('visitor:id,uuid,subject_type,subject_id')
            ->when($device !== null && $device !== '', fn (Builder $q): Builder => $q->where('device_type', $device))
            ->when($source !== null && $source !== '', fn (Builder $q): Builder => $q->where('source', $source))
            ->when($search !== null && $search !== '', function (Builder $query) use ($search, $subjects): void {
                $term = '%'.$search.'%';
                $countryCodes = $this->matchingCountryCodes($search);

                $query->where(function (Builder $inner) use ($term, $search, $subjects, $countryCodes): void {
                    $inner->where('city', 'like', $term)
                        ->orWhere('country', 'like', $term)
                        ->orWhereHas('visitor', fn (Builder $visitor): Builder => $visitor->where('uuid', 'like', $term));

                    if ($countryCodes !== []) {
                        $inner->orWhereIn('country', $countryCodes);
                    }

                    if (ctype_digit($search)) {
                        $inner->orWhere('subject_id', (int) $search);
                    }

                    foreach ($subjects->guards() as $guard) {
                        $ids = $subjects->matchIds($guard, $search);

                        if ($ids !== []) {
                            $inner->orWhere(fn (Builder $q): Builder => $q->where('subject_type', $guard)->whereIn('subject_id', $ids));
                        }
                    }
                });
            });

        if ($sort === 'duration') {
            $query->orderByRaw($this->durationSecondsExpression('started_at', 'last_activity_at').' '.$direction);
        } else {
            $sortable = ['started_at', 'pageview_count', 'source', 'country', 'device_type', 'landing_route'];
            $query->orderBy(in_array($sort, $sortable, true) ? $sort : 'started_at', $direction);
        }

        return $query->paginate($perPage);
    }

    /**
     * ISO country codes stored in sessions whose localised name (or the code
     * itself) matches the term, so the list can be searched by country name.
     *
     * @return list<string>
     */
    private function matchingCountryCodes(string $search): array
    {
        if (! class_exists(\Locale::class)) {
            return [];
        }

        try {
            $codes = Session::query()->whereNotNull('country')->distinct()->pluck('country');
        } catch (\Throwable) {
            return [];
        }

        $locale = app()->getLocale();
        $matches = [];

        foreach ($codes as $code) {
            $code = (string) $code;
            $name = \Locale::getDisplayRegion('-'.$code, $locale);

            if (is_string($name) && $name !== '' && mb_stripos($name, $search) !== false) {
                $matches[] = $code;
            }
        }

        return $matches;
    }

    /**
     * Distinct device types and sources present in the period, for the session
     * filters.
     *
     * @return array{devices: list<string>, sources: list<string>}
     */
    public function sessionFilterOptions(Period $period, ?string $subjectType): array
    {
        $base = fn (string $column): array => $this->sessionScope($period, $subjectType)
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->all();

        return [
            'devices' => $base('device_type'),
            'sources' => $base('source'),
        ];
    }

    /**
     * Scalar aggregates for a period in a single query.
     */
    private function aggregates(Period $period, ?string $subjectType): object
    {
        $duration = $this->durationSecondsExpression('started_at', 'last_activity_at');

        $row = $this->sessionScope($period, $subjectType)
            ->toBase()
            ->selectRaw(
                'COUNT(*) as sessions, '.
                'COUNT(DISTINCT visitor_id) as visitors, '.
                'COALESCE(SUM(pageview_count), 0) as pageviews, '.
                "COALESCE(AVG({$duration}), 0) as avg_seconds, ".
                'COALESCE(SUM(CASE WHEN pageview_count <= 1 THEN 1 ELSE 0 END), 0) as bounces'
            )
            ->first();

        return $row ?? (object) ['sessions' => 0, 'visitors' => 0, 'pageviews' => 0, 'avg_seconds' => 0, 'bounces' => 0];
    }

    /**
     * Rank a session column by count with previous-period comparison.
     *
     * @return list<array{label: string, total: int, previous: int}>
     */
    private function rankedSessionColumn(string $column, Period $period, ?string $subjectType, int $limit): array
    {
        $count = fn (Period $p): Collection => $this->sessionScope($p, $subjectType)
            ->toBase()
            ->whereNotNull($column)
            ->selectRaw("{$column} as label, COUNT(*) as total")
            ->groupBy($column)
            ->pluck('total', 'label');

        return $this->mergeRanked($count($period), $count($period->previous()), $limit);
    }

    /**
     * @return Collection<string, int>
     */
    private function rankedEventCounts(EventType $type, string $column, Period $period, ?string $subjectType): Collection
    {
        return $this->eventScope($type, $period, $subjectType)
            ->whereNotNull($column)
            ->selectRaw("{$column} as label, COUNT(*) as total")
            ->groupBy($column)
            ->pluck('total', 'label');
    }

    /**
     * Merge current and previous keyed counts into a ranked list (busiest
     * first), keeping the previous value for each of the top entries.
     *
     * @param  Collection<string, int>  $current
     * @param  Collection<string, int>  $previous
     * @return list<array{label: string, total: int, previous: int}>
     */
    private function mergeRanked(Collection $current, Collection $previous, int $limit): array
    {
        return $current->map(fn ($total): int => (int) $total)
            ->sortDesc()
            ->take($limit)
            ->map(fn (int $total, string $label): array => [
                'label' => (string) $label,
                'total' => (int) $total,
                'previous' => (int) ($previous[$label] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Base query: non-bot sessions within the period, narrowed to a subject type.
     *
     * @return Builder<Session>
     */
    private function sessionScope(Period $period, ?string $subjectType): Builder
    {
        return Session::query()
            ->where('is_bot', false)
            ->whereBetween('started_at', [$period->from, $period->to])
            ->when($subjectType !== null, fn (Builder $query): Builder => $query->where('subject_type', $subjectType));
    }

    /**
     * Base query for events of a type in the period, excluding bot sessions and
     * scoped to a subject via the parent session (kept consistent with
     * session-based metrics).
     */
    private function eventScope(EventType $type, Period $period, ?string $subjectType): \Illuminate\Database\Query\Builder
    {
        return Event::query()
            ->toBase()
            ->where('type', $type->value)
            ->whereBetween('occurred_at', [$period->from, $period->to])
            ->whereExists(function (\Illuminate\Database\Query\Builder $sub) use ($subjectType): void {
                $sub->selectRaw('1')
                    ->from('falcon_analytics_sessions')
                    ->whereColumn('falcon_analytics_sessions.id', 'falcon_analytics_events.session_id')
                    ->where('is_bot', false)
                    ->when($subjectType !== null, fn (\Illuminate\Database\Query\Builder $s) => $s->where('subject_type', $subjectType));
            });
    }

    /**
     * Driver-aware SQL truncating a timestamp to a 'YYYY-MM-DD' string so daily
     * buckets group identically on every database. The column is a trusted
     * internal constant, never user input.
     */
    private function dayExpression(string $column): string
    {
        return match (Session::query()->getConnection()->getDriverName()) {
            'pgsql' => "to_char({$column}, 'YYYY-MM-DD')",
            'sqlsrv' => "CONVERT(varchar(10), {$column}, 23)",
            default => "DATE({$column})",
        };
    }

    /**
     * Driver-aware SQL for the difference in seconds between two timestamp
     * columns (both trusted internal constants).
     */
    private function durationSecondsExpression(string $start, string $end): string
    {
        return match (Session::query()->getConnection()->getDriverName()) {
            'sqlite' => "(strftime('%s', {$end}) - strftime('%s', {$start}))",
            'pgsql' => "EXTRACT(EPOCH FROM ({$end} - {$start}))",
            'sqlsrv' => "DATEDIFF(SECOND, {$start}, {$end})",
            default => "TIMESTAMPDIFF(SECOND, {$start}, {$end})",
        };
    }
}
