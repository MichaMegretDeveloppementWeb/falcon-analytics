<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Models\DailyArchive;
use Falcon\Analytics\Models\DailyCount;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Repositories\Concerns\ScopesSessionQueries;
use Falcon\Analytics\Services\Dashboard\MarketingReportBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read model for the overview screen: audience mix (new vs returning, devices),
 * the daily traffic trend and the ranked top sources, localities, pages and
 * clicks (each with its previous-period count). Bots excluded.
 *
 * @internal
 */
final readonly class OverviewReadRepository
{
    use ScopesSessionQueries;

    public function __construct(private MarketingReportBuilder $marketing) {}

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
     * Top acquisition channels with their previous-period counts. Every session
     * matching a defined marketing campaign is counted as paid (deduplicated with
     * the generic paid signals), and the residual 'campaign' source folds into
     * referral, so the channel mix reconciles with the marketing module.
     *
     * @return list<array{label: string, total: int, previous: int}>
     */
    public function topSources(Period $period, ?string $subjectType, int $limit = 6): array
    {
        $campaigns = array_values(Campaign::query()->where('is_active', true)->get()->all());

        return $this->mergeRanked(
            $this->channelCounts($period, $subjectType, $campaigns),
            $this->channelCounts($period->previous(), $subjectType, $campaigns),
            $limit,
        );
    }

    /**
     * Session counts per acquisition channel: the raw source, with 'campaign'
     * folded into referral and every campaign-matched session reclassified as paid.
     *
     * @param  list<Campaign>  $campaigns
     * @return Collection<string, int>
     */
    private function channelCounts(Period $period, ?string $subjectType, array $campaigns): Collection
    {
        /** @var array<string, int> $counts */
        $counts = [];

        $raw = $this->sessionScope($period, $subjectType)
            ->toBase()
            ->whereNotNull('source')
            ->selectRaw('source as label, COUNT(*) as total')
            ->groupBy('source')
            ->pluck('total', 'label');

        foreach ($raw as $source => $total) {
            $channel = $source === 'campaign' ? 'referral' : (string) $source;
            $counts[$channel] = ($counts[$channel] ?? 0) + (int) $total;
        }

        if ($campaigns === []) {
            return collect($counts);
        }

        foreach ($this->marketing->matchedSessionSources($period, $subjectType, $campaigns) as $source) {
            $channel = $source === 'campaign' ? 'referral' : $source;
            if ($channel === 'paid') {
                continue;
            }

            $counts[$channel] = max(0, ($counts[$channel] ?? 0) - 1);
            if ($counts[$channel] === 0) {
                unset($counts[$channel]);
            }
            $counts['paid'] = ($counts['paid'] ?? 0) + 1;
        }

        return collect($counts);
    }

    /**
     * The ceiling, when the attribution behind the paid share of `topSources()`
     * read only part of a period's sessions, or null when it read them all.
     */
    public function attributionTruncatedAt(Period $period, ?string $subjectType): ?int
    {
        return $this->marketing->truncatedAt($period, $subjectType)
            ?? $this->marketing->truncatedAt($period->previous(), $subjectType);
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

        // `array_values` only to carry the `list` type: the keys already run from zero.
        return array_values($counts($period)
            ->sortByDesc(fn (object $row): int => (int) $row->total)
            ->take($limit)
            ->map(fn (object $row): array => [
                'country' => (string) $row->country,
                'city' => ($row->city !== null && $row->city !== '') ? (string) $row->city : null,
                'total' => (int) $row->total,
                'previous' => (int) ($previous[$row->country.'|'.($row->city ?? '')]->total ?? 0),
            ])
            ->all());
    }

    /**
     * The most viewed pages, with the previous-period count for each · a page
     * being the path of its address, without host, query string or fragment.
     * See `StoredUrl`.
     *
     * @return list<array{label: string, total: int, previous: int}>
     */
    public function topPages(Period $period, ?string $subjectType, int $limit = 6): array
    {
        $current = $this->pageCounts($period, $subjectType);
        $previous = $this->pageCounts($period->previous(), $subjectType);

        return $this->mergeRanked($current, $previous, $limit);
    }

    /**
     * Page views by page path, a closed day from its summary and the day under
     * way from its rows.
     *
     * The two halves never overlap and never leave a gap. Up to the last day
     * summarised, the figures come from the summaries; after it, from the rows
     * themselves, so the rows are read for a day or so whatever the period,
     * never across the whole retention.
     *
     * A closed day is therefore counted as the night counted it · a row erased
     * or a session identified after that does not reach these two blocks.
     *
     * A summarised day still holds its rows, which the summary counted too, so
     * the split has to be strict.
     *
     * @return Collection<string, int>
     */
    private function pageCounts(Period $period, ?string $subjectType): Collection
    {
        return $this->joinHalves(
            fn (Period $window): Collection => $this->rankedPageCounts($window, $subjectType),
            fn (Period $window): Collection => $this->summarisedCounts($window, $subjectType, DailyCount::KIND_PAGE)
                ->mapWithKeys(fn (array $row): array => [$row['label'] => $row['total']]),
            $period,
        );
    }

    /**
     * The reading of one measure over a period, taken from the summaries for
     * the days they hold and from the detail for the rest, then added up.
     *
     * Adding is exact here and only here · these are plain counts, so a day
     * plus a day makes two days. Nothing that counts DISTINCT visitors could
     * be split this way, and nothing that does is summarised.
     *
     * @param  callable(Period): Collection<string, int>  $fromDetail
     * @param  callable(Period): Collection<string, int>  $fromSummary
     * @return Collection<string, int>
     */
    private function joinHalves(callable $fromDetail, callable $fromSummary, Period $period): Collection
    {
        $lastSummarised = DailyArchive::lastSummarisedDay();

        if ($lastSummarised === null || $lastSummarised->lessThan($period->from)) {
            return $fromDetail($period);
        }

        $boundary = $lastSummarised->endOfDay();

        if ($boundary->greaterThanOrEqualTo($period->to)) {
            return $fromSummary($period);
        }

        $summarised = $fromSummary(new Period($period->from, $boundary, $period->days));
        $detailed = $fromDetail(new Period($boundary->addSecond(), $period->to, $period->days));

        foreach ($detailed as $label => $total) {
            $summarised[$label] = ($summarised[$label] ?? 0) + $total;
        }

        return $summarised;
    }

    /**
     * A measure read off the daily summaries, added across the window.
     *
     * @return Collection<int, array{label: string, route: string|null, total: int}>
     */
    private function summarisedCounts(Period $period, ?string $subjectType, string $kind): Collection
    {
        /** @var Collection<int, array{label: string, route: string|null, total: int}> $rows */
        $rows = DailyCount::query()
            ->where('kind', $kind)
            ->whereBetween('day', [$period->from->toDateString(), $period->to->toDateString()])
            ->when($subjectType !== null, fn (Builder $query): Builder => $query->where('subject_type', $subjectType))
            ->selectRaw('label, route, SUM(total) as total')
            ->groupBy('label', 'route')
            ->get()
            ->map(fn (DailyCount $row): array => [
                'label' => $row->label,
                'route' => $row->route,
                'total' => (int) $row->getAttribute('total'),
            ]);

        return $rows;
    }

    /**
     * Most clicked elements, each keyed by its event (its text for a plain
     * click) + the page it sits on + its count.
     *
     * @return list<array{label: string, route: ?string, total: int}>
     */
    public function topClicks(Period $period, ?string $subjectType, int $limit = 6): array
    {
        // Keyed by label AND route: two buttons that read the same on different pages stay apart.
        $counts = $this->joinHalves(
            fn (Period $window): Collection => $this->detailedClickCounts($window, $subjectType),
            fn (Period $window): Collection => $this->summarisedCounts($window, $subjectType, DailyCount::KIND_CLICK)
                ->mapWithKeys(fn (array $row): array => [self::clickKey($row['label'], $row['route']) => $row['total']]),
            $period,
        );

        return array_values($counts
            ->sortDesc()
            ->take($limit)
            ->map(function (int $total, string $key): array {
                [$label, $route] = self::splitClickKey($key);

                return ['label' => $label, 'route' => $route, 'total' => $total];
            })
            ->all());
    }

    /**
     * Clicks of the window, straight from the rows.
     *
     * @return Collection<string, int>
     */
    private function detailedClickCounts(Period $period, ?string $subjectType): Collection
    {
        // A named click counts by its event, a plain one by its text · the daily summary keys it the same way.
        $label = "COALESCE(NULLIF(name, ''), NULLIF(target_text, ''))";

        // Grouped from a subquery: ONLY_FULL_GROUP_BY rejects grouping by the COALESCE itself.
        $clicks = $this->eventScope(EventType::Click, $period, $subjectType)
            ->selectRaw("{$label} as label, route");

        /** @var Collection<string, int> $counts */
        $counts = DB::query()
            ->fromSub($clicks, 'clicks')
            ->whereNotNull('label')
            ->selectRaw('label, route, COUNT(*) as total')
            ->groupBy('label', 'route')
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                self::clickKey((string) $row->label, $row->route !== null ? (string) $row->route : null) => (int) $row->total,
            ]);

        return $counts;
    }

    /**
     * A click's identity, as one string · the route, then the label.
     *
     * Not label-first: a label comes from `textContent`, which keeps the line
     * feeds of the source, so it can hold the separator while a route name
     * cannot. The label goes last, and the split takes everything after the
     * first separator.
     */
    private static function clickKey(string $label, ?string $route): string
    {
        return ($route ?? '')."\n".$label;
    }

    /** @return array{0: string, 1: string|null} */
    private static function splitClickKey(string $key): array
    {
        $parts = explode("\n", $key, 2);
        $route = $parts[0];

        return [$parts[1] ?? '', $route === '' ? null : $route];
    }

    /**
     * Page views of the window, by page · the path of the address, written down
     * at ingestion by the same reading the screen makes to display an address.
     * See `StoredUrl`.
     *
     * @return Collection<string, int>
     */
    private function rankedPageCounts(Period $period, ?string $subjectType): Collection
    {
        /** @var Collection<string, int> $counts */
        $counts = $this->eventScope(EventType::Pageview, $period, $subjectType)
            ->whereNotNull('page')
            ->selectRaw('page as label, COUNT(*) as total')
            ->groupBy('page')
            ->pluck('total', 'label');

        return $counts;
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
        return array_values($current
            ->sortDesc()
            ->take($limit)
            ->map(fn (int $total, string $label): array => [
                'label' => $label,
                'total' => $total,
                'previous' => $previous[$label] ?? 0,
            ])
            ->all());
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
