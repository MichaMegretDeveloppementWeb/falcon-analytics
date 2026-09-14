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

        // `array_values` rather than `->values()`: same result, but it carries
        // the `list` type this file declares. Same reason everywhere here.
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
     * Most viewed pages (pageview events grouped by their real URL, so dynamic
     * pages show their concrete path), with the previous-period count for each.
     *
     * @return list<array{label: string, total: int, previous: int}>
     */
    /**
     * The most seen pages · a page being the path of its route, without host,
     * query string or fragment.
     *
     * The address is stored whole, and a session's journey shows it whole. This
     * block asks another question · on a site receiving campaign traffic,
     * grouping on the whole address split the real top page into one row per
     * visit — `fbclid` being unique per click — while the screen displayed the
     * same path on every one of them. Anchor links did the same. See
     * `StoredUrl`.
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
     * Page views by address, from wherever they are still readable.
     *
     * **The two halves never overlap and never leave a gap.** Up to the last
     * day the purge has emptied, the figures come from that day's summary;
     * after it, from the rows themselves. The line is asked of the archive
     * table rather than worked out from the retention, because the two part
     * company as soon as a scheduler stops or a retention is shortened — and a
     * line in the wrong place would either double a figure or drop one, with
     * nothing to say which.
     *
     * A day whose detail is gone still holds its NAMED rows, which the summary
     * counted too. That is the whole reason the split has to be strict.
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
     * the days that no longer hold their detail and from the detail for the
     * rest, then added up.
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
        $lastErased = DailyArchive::lastPrunedDay();

        // Nothing has ever been erased, so the rows answer for everything.
        if ($lastErased === null || $lastErased->lessThan($period->from)) {
            return $fromDetail($period);
        }

        $boundary = $lastErased->endOfDay();

        // The whole window is behind the line: the summaries answer for it all.
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
     * Most clicked elements (click events), each as a label + the page it sits
     * on + its count.
     *
     * @return list<array{label: string, route: ?string, total: int}>
     */
    public function topClicks(Period $period, ?string $subjectType, int $limit = 6): array
    {
        /*
         * A click is ranked by its label AND the page it sits on, so the two
         * travel together through the joining · a key that dropped the route
         * would merge two different buttons that happen to read the same, and
         * the reading would change the day the purge crossed them.
         */
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
        // Prefer the visible button text (human-readable) over the technical event name.
        $label = "COALESCE(NULLIF(target_text, ''), NULLIF(name, ''))";

        // Resolve the label in a subquery so the aggregate groups by a plain
        // column: MySQL/MariaDB in ONLY_FULL_GROUP_BY reject grouping by this
        // COALESCE expression directly (1055 "target_text isn't in GROUP BY").
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
     * **That order is the whole correctness of this pair**, and it is not the
     * order one writes first. A button's visible text comes from `textContent`,
     * which keeps the line feeds of the source · a button written across three
     * lines of HTML carries them into its label. A route name cannot.
     *
     * So the field that may hold the separator goes LAST, and the split takes
     * everything after the first one. Written label-first, « Demander\nun
     * devis » on the route `accueil` came back as the label « Demander » on the
     * route « un devis\naccueil » — a wrong label and a wrong page, on a block
     * nobody would think to doubt.
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
     * Page views of the window, by page · the path of the route, written down
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
