<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories\Dashboard;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Repositories\Concerns\ScopesSessionQueries;
use Illuminate\Support\Facades\DB;

/**
 * Read model for the engagement headline shared by the overview and sessions
 * screens: raw counts and daily rows (bots excluded). No derivation; the
 * EngagementMetricsCalculator turns these into deltas, ratios and sparklines.
 */
final readonly class EngagementReadRepository
{
    use ScopesSessionQueries;

    /**
     * Raw engagement counts for a period in a single query: distinct visitors,
     * sessions, page views, average duration and bounces.
     *
     * @return array{visitors: int, sessions: int, pageviews: int, avgSeconds: float, bounces: int}
     */
    public function headlineCounts(Period $period, ?string $subjectType): array
    {
        $row = $this->aggregates($period, $subjectType);

        return [
            'visitors' => (int) $row->visitors,
            'sessions' => (int) $row->sessions,
            'pageviews' => (int) $row->pageviews,
            'avgSeconds' => (float) $row->avg_seconds,
            'bounces' => (int) $row->bounces,
        ];
    }

    /**
     * Raw engagement counts for today and yesterday, feeding the "X today,
     * Y yesterday" mini-line. Duration uses last_activity (never now), so open
     * sessions cannot inflate it.
     *
     * @return array{today: array{visitors: int, sessions: int, pageviews: int, avgSeconds: float, bounces: int}, yesterday: array{visitors: int, sessions: int, pageviews: int, avgSeconds: float, bounces: int}}
     */
    public function spotlightCounts(?string $subjectType): array
    {
        $now = CarbonImmutable::now();

        return [
            'today' => $this->headlineCounts(new Period($now->startOfDay(), $now, 1), $subjectType),
            'yesterday' => $this->headlineCounts(new Period($now->subDay()->startOfDay(), $now->subDay()->endOfDay(), 1), $subjectType),
        ];
    }

    /**
     * Raw per-day engagement rows for the period, keyed by 'Y-m-d'. No zero-fill
     * or ratio; the EngagementMetricsCalculator builds the sparkline series.
     *
     * @return array<string, array{sessions: int, visitors: int, pageviews: int, avgSeconds: float, bounces: int}>
     */
    public function sparklineRows(Period $period, ?string $subjectType): array
    {
        $day = $this->dayExpression('started_at');
        $duration = $this->durationSecondsExpression('started_at', 'last_activity_at');

        return $this->sessionScope($period, $subjectType)
            ->toBase()
            ->selectRaw("{$day} as day, COUNT(*) as sessions, COUNT(DISTINCT visitor_id) as visitors, COALESCE(SUM(pageview_count), 0) as pageviews, COALESCE(AVG({$duration}), 0) as avg_seconds, SUM(CASE WHEN pageview_count <= 1 THEN 1 ELSE 0 END) as bounces")
            ->groupBy(DB::raw($day))
            ->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->day => [
                'sessions' => (int) $row->sessions,
                'visitors' => (int) $row->visitors,
                'pageviews' => (int) $row->pageviews,
                'avgSeconds' => (float) $row->avg_seconds,
                'bounces' => (int) $row->bounces,
            ]])
            ->all();
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
}
