<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories;

use Falcon\Analytics\DTOs\Dashboard\OverviewMetrics;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\DTOs\Dashboard\TrendPoint;
use Falcon\Analytics\Models\Session;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Read model for the dashboard pages. Every figure is drawn from the sessions
 * table (never the raw events), excludes sessions flagged as bots, and is
 * scoped to a Period and optional subject type. Event-level reads (behaviour,
 * funnels) are added with the funnel evaluation lot.
 */
final readonly class DashboardReadRepository
{
    public function metrics(Period $period, ?string $subjectType): OverviewMetrics
    {
        $base = fn (): Builder => $this->sessionScope($period, $subjectType);

        return new OverviewMetrics(
            visitors: $base()->distinct()->count('visitor_id'),
            sessions: $base()->count(),
            pageviews: (int) $base()->sum('pageview_count'),
        );
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
     * Most frequent entry pages (landing route), busiest first.
     *
     * @return list<array{route: string, total: int}>
     */
    public function topLandingPages(Period $period, ?string $subjectType, int $limit = 6): array
    {
        return $this->sessionScope($period, $subjectType)
            ->toBase()
            ->whereNotNull('landing_route')
            ->selectRaw('landing_route, COUNT(*) as total')
            ->groupBy('landing_route')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => ['route' => (string) $row->landing_route, 'total' => (int) $row->total])
            ->all();
    }

    /**
     * Most frequent acquisition sources, busiest first.
     *
     * @return list<array{source: string, total: int}>
     */
    public function topSources(Period $period, ?string $subjectType, int $limit = 6): array
    {
        return $this->sessionScope($period, $subjectType)
            ->toBase()
            ->whereNotNull('source')
            ->selectRaw('source, COUNT(*) as total')
            ->groupBy('source')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => ['source' => (string) $row->source, 'total' => (int) $row->total])
            ->all();
    }

    /**
     * Paginated session list for the sessions page, newest first, optionally
     * filtered by a free-text term matched against IP and locality.
     *
     * @return LengthAwarePaginator<int, Session>
     */
    public function paginateSessions(Period $period, ?string $subjectType, ?string $search, int $perPage = 20): LengthAwarePaginator
    {
        return $this->sessionScope($period, $subjectType)
            ->with('visitor:id,uuid,subject_type,subject_id')
            ->when($search !== null && $search !== '', function (Builder $query) use ($search): void {
                $term = '%'.$search.'%';

                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('ip', 'like', $term)
                        ->orWhere('city', 'like', $term)
                        ->orWhere('country', 'like', $term);
                });
            })
            ->latest('started_at')
            ->paginate($perPage);
    }

    /**
     * Base query shared by every dashboard read: non-bot sessions within the
     * period, narrowed to a subject type when one is selected.
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
     * Driver-aware SQL that truncates a timestamp column to a 'YYYY-MM-DD'
     * string, so daily buckets group and sort identically on every database.
     * The column name is a trusted internal constant, never user input.
     */
    private function dayExpression(string $column): string
    {
        return match (Session::query()->getConnection()->getDriverName()) {
            'pgsql' => "to_char({$column}, 'YYYY-MM-DD')",
            'sqlsrv' => "CONVERT(varchar(10), {$column}, 23)",
            default => "DATE({$column})",
        };
    }
}
