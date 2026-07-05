<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Repositories\Concerns\ScopesSessionQueries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read model for the overview screen: audience mix (new vs returning, devices),
 * the daily traffic trend and the ranked top sources, localities, pages and
 * clicks (each with its previous-period count). Bots excluded.
 */
final readonly class OverviewReadRepository
{
    use ScopesSessionQueries;

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
     * Raw daily session and page-view counts across the period, keyed by
     * 'Y-m-d'. No zero-fill; the TrendSeriesCalculator builds the point list.
     *
     * @return array<string, array{sessions: int, pageviews: int}>
     */
    public function trendRows(Period $period, ?string $subjectType): array
    {
        $day = $this->dayExpression('started_at');

        return $this->sessionScope($period, $subjectType)
            ->toBase()
            ->selectRaw("{$day} as day, COUNT(*) as sessions, COALESCE(SUM(pageview_count), 0) as pageviews")
            ->groupBy(DB::raw($day))
            ->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->day => [
                'sessions' => (int) $row->sessions,
                'pageviews' => (int) $row->pageviews,
            ]])
            ->all();
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
     * Most viewed pages (pageview events grouped by their real URL, so dynamic
     * pages show their concrete path), with the previous-period count for each.
     *
     * @return list<array{label: string, total: int, previous: int}>
     */
    public function topPages(Period $period, ?string $subjectType, int $limit = 6): array
    {
        $current = $this->rankedEventCounts(EventType::Pageview, 'url', $period, $subjectType);
        $previous = $this->rankedEventCounts(EventType::Pageview, 'url', $period->previous(), $subjectType);

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

        // Resolve the label in a subquery so the aggregate groups by a plain
        // column: MySQL/MariaDB in ONLY_FULL_GROUP_BY reject grouping by this
        // COALESCE expression directly (1055 "target_text isn't in GROUP BY").
        $clicks = $this->eventScope(EventType::Click, $period, $subjectType)
            ->selectRaw("{$label} as label, route");

        return DB::query()
            ->fromSub($clicks, 'clicks')
            ->whereNotNull('label')
            ->selectRaw('label, route, COUNT(*) as total')
            ->groupBy('label', 'route')
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
}
