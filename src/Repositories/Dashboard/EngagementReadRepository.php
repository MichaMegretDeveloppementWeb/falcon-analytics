<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories\Dashboard;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Repositories\Concerns\ScopesSessionQueries;
use Falcon\Analytics\Services\RetentionWindow;
use Illuminate\Support\Facades\DB;

/**
 * Read model for the engagement headline shared by the overview and sessions
 * screens: raw counts and daily rows (bots excluded). No derivation; the
 * EngagementMetricsCalculator turns these into deltas, ratios and sparklines.
 *
 * Days whose sessions the purge has erased are read from their daily totals ·
 * the counts add up across the line, the distinct visitors do not, and are
 * then left out.
 *
 * @internal
 */
final readonly class EngagementReadRepository
{
    use ScopesSessionQueries;

    public function __construct(
        private RetentionWindow $window = new RetentionWindow,
        private DailyTotalsReadRepository $totals = new DailyTotalsReadRepository,
    ) {}

    /**
     * Raw engagement counts for a period: distinct visitors, sessions, page
     * views, average duration and bounces · the visitors null when the period
     * reaches days read from their totals.
     *
     * @return array{visitors: int|null, sessions: int, pageviews: int, avgSeconds: float, bounces: int}
     */
    public function headlineCounts(Period $period, ?string $subjectType): array
    {
        ['totals' => $summarised, 'rows' => $detailed] = RetentionWindow::split($period, $this->window->sessionsLine());

        $read = $detailed === null ? null : $this->aggregates($detailed, $subjectType);
        $added = $summarised === null ? null : $this->totals->engagement($summarised, $subjectType);

        $sessions = ($read['sessions'] ?? 0) + ($added['sessions'] ?? 0);
        $seconds = ($read['seconds'] ?? 0) + ($added['seconds'] ?? 0);

        return [
            'visitors' => $added === null ? ($read['visitors'] ?? 0) : null,
            'sessions' => $sessions,
            'pageviews' => ($read['pageviews'] ?? 0) + ($added['pageviews'] ?? 0),
            'avgSeconds' => $sessions > 0 ? $seconds / $sessions : 0.0,
            'bounces' => ($read['bounces'] ?? 0) + ($added['bounces'] ?? 0),
        ];
    }

    /**
     * Raw engagement counts for today and yesterday, feeding the "X today,
     * Y yesterday" mini-line. Duration uses last_activity (never now), so open
     * sessions cannot inflate it.
     *
     * @return array{today: array{visitors: int|null, sessions: int, pageviews: int, avgSeconds: float, bounces: int}, yesterday: array{visitors: int|null, sessions: int, pageviews: int, avgSeconds: float, bounces: int}}
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
     * Raw per-day engagement rows for the period, keyed by 'Y-m-d' · a day read
     * from its totals has no distinct visitors. No zero-fill or ratio; the
     * EngagementMetricsCalculator builds the sparkline series.
     *
     * @return array<string, array{sessions: int, visitors: int|null, pageviews: int, avgSeconds: float, bounces: int}>
     */
    public function sparklineRows(Period $period, ?string $subjectType): array
    {
        ['totals' => $summarised, 'rows' => $detailed] = RetentionWindow::split($period, $this->window->sessionsLine());

        $days = [];

        foreach ($summarised === null ? [] : $this->totals->engagementByDay($summarised, $subjectType) as $day => $row) {
            $days[$day] = [
                'sessions' => $row['sessions'],
                'visitors' => null,
                'pageviews' => $row['pageviews'],
                'avgSeconds' => $row['sessions'] > 0 ? $row['seconds'] / $row['sessions'] : 0.0,
                'bounces' => $row['bounces'],
            ];
        }

        return $detailed === null ? $days : [...$days, ...$this->dailyRows($detailed, $subjectType)];
    }

    /**
     * @return array<string, array{sessions: int, visitors: int, pageviews: int, avgSeconds: float, bounces: int}>
     */
    private function dailyRows(Period $period, ?string $subjectType): array
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
     * Scalar aggregates for a period in a single query · the seconds summed, so
     * they add up with those of the totals.
     *
     * Returned as an array, which carries its shape: the `stdClass` from
     * `first()` takes its properties from the SELECT aliases, which static
     * analysis cannot know.
     *
     * @return array{sessions: int, visitors: int, pageviews: int, seconds: int, bounces: int}
     */
    private function aggregates(Period $period, ?string $subjectType): array
    {
        $duration = $this->durationSecondsExpression('started_at', 'last_activity_at');

        $row = $this->sessionScope($period, $subjectType)
            ->toBase()
            ->selectRaw(
                'COUNT(*) as sessions, '.
                'COUNT(DISTINCT visitor_id) as visitors, '.
                'COALESCE(SUM(pageview_count), 0) as pageviews, '.
                "COALESCE(SUM({$duration}), 0) as seconds, ".
                'COALESCE(SUM(CASE WHEN pageview_count <= 1 THEN 1 ELSE 0 END), 0) as bounces'
            )
            ->first();

        if ($row === null) {
            return ['sessions' => 0, 'visitors' => 0, 'pageviews' => 0, 'seconds' => 0, 'bounces' => 0];
        }

        return [
            'sessions' => (int) $row->sessions,
            'visitors' => (int) $row->visitors,
            'pageviews' => (int) $row->pageviews,
            'seconds' => (int) $row->seconds,
            'bounces' => (int) $row->bounces,
        ];
    }
}
