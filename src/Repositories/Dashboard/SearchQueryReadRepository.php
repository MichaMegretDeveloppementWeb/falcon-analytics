<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories\Dashboard;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Models\SearchQuery;

/**
 * Reads for the "Clics par recherches Google" section, on the locally synced
 * Search Console cache only (never the API): the top queries of the period
 * and the cache's freshness edge. GSC data trails reality by ~3 days, so the
 * freshest cached day is surfaced next to the numbers.
 */
final class SearchQueryReadRepository
{
    /**
     * Top queries of the period by clicks, each with its previous-period
     * clicks for the delta. Positions are averaged weighted by impressions,
     * so a day with real visibility outweighs a stray one.
     *
     * @return list<array{query: string, clicks: int, impressions: int, ctr: float|null, position: float|null, previous: int}>
     */
    public function topQueries(Period $period, int $limit): array
    {
        $rows = SearchQuery::query()
            ->selectRaw('query')
            ->selectRaw('SUM(clicks) as total_clicks')
            ->selectRaw('SUM(impressions) as total_impressions')
            ->selectRaw('SUM(position * impressions) as weighted_position')
            ->whereBetween('date', [$period->from->toDateString(), $period->to->toDateString()])
            ->groupBy('query')
            ->orderByDesc('total_clicks')
            ->orderByDesc('total_impressions')
            ->limit($limit)
            ->get();

        $previousPeriod = $period->previous();
        $previous = SearchQuery::query()
            ->selectRaw('query')
            ->selectRaw('SUM(clicks) as total_clicks')
            ->whereBetween('date', [$previousPeriod->from->toDateString(), $previousPeriod->to->toDateString()])
            ->whereIn('query', $rows->pluck('query'))
            ->groupBy('query')
            ->pluck('total_clicks', 'query');

        // `array_values` · le resultat est deja indexe depuis zero, mais son
        // type ne le dit pas et cette methode declare une liste.
        return array_values($rows->map(function (SearchQuery $row) use ($previous): array {
            $clicks = (int) $row->getAttribute('total_clicks');
            $impressions = (int) $row->getAttribute('total_impressions');
            $weighted = (float) $row->getAttribute('weighted_position');

            return [
                'query' => $row->query,
                'clicks' => $clicks,
                'impressions' => $impressions,
                'ctr' => $impressions > 0 ? round($clicks / $impressions * 100, 1) : null,
                'position' => $impressions > 0 ? round($weighted / $impressions, 1) : null,
                'previous' => (int) ($previous[$row->query] ?? 0),
            ];
        })->all());
    }

    /**
     * Total organic clicks of the period and of the one before, for the
     * headline number and its delta.
     *
     * @return array{current: int, previous: int}
     */
    public function clicksTotals(Period $period): array
    {
        $previousPeriod = $period->previous();

        $sum = fn (CarbonImmutable $from, CarbonImmutable $to): int => (int) SearchQuery::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->sum('clicks');

        return [
            'current' => $sum($period->from, $period->to),
            'previous' => $sum($previousPeriod->from, $previousPeriod->to),
        ];
    }

    /**
     * The freshest cached day within the period, or null when the period holds
     * no data at all.
     */
    public function freshestDate(Period $period): ?CarbonImmutable
    {
        $max = SearchQuery::query()
            ->whereBetween('date', [$period->from->toDateString(), $period->to->toDateString()])
            ->max('date');

        return is_string($max) && $max !== '' ? CarbonImmutable::parse($max) : null;
    }
}
