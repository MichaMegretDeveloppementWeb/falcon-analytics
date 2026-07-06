<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\Dashboard;

/**
 * Pure calculator that derives a visitor's headline engagement figures from the
 * raw session aggregates, so the component stays orchestration-only (mirrors the
 * repo/calculator split of the other dashboard screens).
 */
final class VisitorEngagementCalculator
{
    /**
     * @param  array{sessions: int, pageviews: int, seconds: int, devices: array<string, int>, sources: array<string, int>}  $engagement
     * @return array{totalPageviews: int, avgSeconds: int, pagesPerSession: float, devices: array<string, int>, sources: array<string, int>}
     */
    public function summarize(array $engagement): array
    {
        $count = $engagement['sessions'];

        return [
            'totalPageviews' => $engagement['pageviews'],
            'avgSeconds' => $count > 0 ? (int) round($engagement['seconds'] / $count) : 0,
            'pagesPerSession' => $count > 0 ? round($engagement['pageviews'] / $count, 1) : 0.0,
            'devices' => $engagement['devices'],
            'sources' => $engagement['sources'],
        ];
    }
}
