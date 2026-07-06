<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\Dashboard;

use Falcon\Analytics\Models\Session;
use Illuminate\Support\Collection;

/**
 * Pure calculator that summarises a visitor's sessions into headline engagement
 * figures and device/source breakdowns, so the component stays orchestration-only
 * (mirrors SessionJourneyBuilder for the session detail).
 */
final class VisitorEngagementCalculator
{
    /**
     * @param  Collection<int, Session>  $sessions
     * @return array{totalPageviews: int, avgSeconds: int, pagesPerSession: float, devices: array<string, int>, sources: array<string, int>}
     */
    public function summarize(Collection $sessions): array
    {
        $count = $sessions->count();
        $totalPageviews = (int) $sessions->sum('pageview_count');
        $totalSeconds = (int) $sessions->sum(
            fn (Session $s): int => abs((int) $s->started_at->diffInSeconds($s->last_activity_at)),
        );

        return [
            'totalPageviews' => $totalPageviews,
            'avgSeconds' => $count > 0 ? (int) round($totalSeconds / $count) : 0,
            'pagesPerSession' => $count > 0 ? round($totalPageviews / $count, 1) : 0.0,
            'devices' => $sessions->groupBy(fn (Session $s): string => (string) $s->device_type)->map->count()->sortDesc()->all(),
            'sources' => $sessions->groupBy(fn (Session $s): string => $s->source ?: 'direct')->map->count()->sortDesc()->all(),
        ];
    }
}
