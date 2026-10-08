<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\DTOs\Dashboard\TaggedGroup;
use Falcon\Analytics\DTOs\Dashboard\TaggedSessions;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Repositories\Dashboard\DailyTotalsReadRepository;
use Falcon\Analytics\Repositories\Dashboard\MarketingReadRepository;
use Falcon\Analytics\Services\RetentionWindow;
use Illuminate\Database\Eloquent\Collection;

/**
 * Builds the marketing reports from the repository's raw rows. Sessions carry
 * the raw landing-URL parameters (mkt_params); campaigns and ads are matched to
 * them by their conditions (all must hold, AND; the most specific rule wins) at
 * report time, so an ad defined after the fact still sees its full history.
 * Attribution and every aggregate (headline, daily trend, performance,
 * campaign/ad reports) live here; crediting the conversions is
 * `MarketingConversionCrediter`'s, handed the attribution. The repository only
 * reads.
 *
 * Days whose sessions are erased are read from their daily totals, the
 * parameters each group of sessions arrived with letting the campaigns claim
 * them all the same · the sessions add up, the distinct visitors and the
 * conversions, which count people, are then unknown.
 *
 * @internal
 */
final class MarketingReportBuilder
{
    /**
     * Active campaigns and ads, loaded once per instance. A dashboard render
     * calls several report methods that each need the same lists; the builder is
     * resolved once per request (not a singleton), so memoizing here avoids
     * reloading them.
     *
     * @var list<Campaign>|null
     */
    private ?array $activeCampaigns = null;

    /** @var list<Ad>|null */
    private ?array $activeAds = null;

    /** @var Collection<int, Ad>|null */
    private ?Collection $activeAdsWithObjectives = null;

    /** @var array<string, TaggedSessions> */
    private array $taggedSessionCache = [];

    /** @var array<string, list<TaggedGroup>> */
    private array $taggedGroupCache = [];

    /**
     * The collaborator defaults so a plain `new MarketingReportBuilder` still
     * works (tests, ad-hoc use); the container injects the shared services
     * otherwise.
     */
    public function __construct(
        private MarketingReadRepository $repository = new MarketingReadRepository,
        private AttributionResolver $attribution = new AttributionResolver,
        private MarketingConversionCrediter $crediter = new MarketingConversionCrediter,
        private RetentionWindow $window = new RetentionWindow,
        private DailyTotalsReadRepository $totals = new DailyTotalsReadRepository,
    ) {}

    /**
     * Ad-tagged sessions for a period, loaded once and reused. headline,
     * dailySessions, performance and conversions all scan the same tagged
     * sessions to match them to campaigns/ads; caching them (per period +
     * subject) avoids repeating that read on every render.
     *
     * @return Collection<int, Session>
     */
    private function taggedSessionRows(Period $period, ?string $subjectType): Collection
    {
        $key = self::cacheKey($period, $subjectType);

        return ($this->taggedSessionCache[$key] ??= $this->repository->taggedSessionRows($period, $subjectType))->rows;
    }

    /**
     * The period's tagged sessions as groups · each session of the days whose
     * rows are kept, with its visitor, and each group of the days read from
     * their totals, with none. Read once per period and subject.
     *
     * @return list<TaggedGroup>
     */
    private function taggedGroups(Period $period, ?string $subjectType): array
    {
        return $this->taggedGroupCache[self::cacheKey($period, $subjectType)] ??= $this->readTaggedGroups($period, $subjectType);
    }

    /**
     * @return list<TaggedGroup>
     */
    private function readTaggedGroups(Period $period, ?string $subjectType): array
    {
        ['totals' => $summarised, 'rows' => $detailed] = RetentionWindow::split($period, $this->window->sessionsLine());

        $groups = [];

        foreach ($summarised === null ? [] : $this->totals->sessionsWithParameters($summarised, $subjectType) as $group) {
            $groups[] = new TaggedGroup($group['params'], $group['day'], null, $group['sessions']);
        }

        foreach ($detailed === null ? [] : $this->taggedSessionRows($detailed, $subjectType) as $session) {
            $groups[] = new TaggedGroup($session->mkt_params ?? [], $session->started_at->toDateString(), $session->visitor_id, 1);
        }

        return $groups;
    }

    /**
     * The groups one of the candidates claims, by the id of the one that does.
     *
     * @param  list<TaggedGroup>  $groups
     * @param  list<Campaign>|list<Ad>  $candidates
     * @return array<int, list<TaggedGroup>>
     */
    private function claimedBy(array $groups, array $candidates): array
    {
        $claimed = [];

        foreach ($groups as $group) {
            $winner = $this->attribution->mostSpecific($candidates, $group->params);

            if ($winner !== null) {
                $claimed[$winner->id][] = $group;
            }
        }

        return $claimed;
    }

    /**
     * The ceiling, when the sessions this builder served for that period were
     * cut at it, or null when they were whole or never read. A screen asks it
     * after building its figures, so nothing is read for the answer.
     */
    public function truncatedAt(Period $period, ?string $subjectType): ?int
    {
        $detailed = RetentionWindow::split($period, $this->window->sessionsLine())['rows'];
        $read = $detailed === null ? null : $this->taggedSessionCache[self::cacheKey($detailed, $subjectType)] ?? null;

        return $read !== null && $read->truncated ? $read->ceiling : null;
    }

    private static function cacheKey(Period $period, ?string $subjectType): string
    {
        return $period->from->format('c').'|'.$period->to->format('c').'|'.($subjectType ?? '');
    }

    /**
     * @return list<Campaign>
     */
    private function activeCampaigns(): array
    {
        return $this->activeCampaigns ??= $this->repository->activeCampaigns();
    }

    /**
     * @return list<Ad>
     */
    private function activeAds(): array
    {
        // `array_values` only to carry the `list` type: the keys already run from zero.
        return $this->activeAds ??= array_values($this->activeAdsWithObjectives()->all());
    }

    /**
     * @return Collection<int, Ad>
     */
    private function activeAdsWithObjectives(): Collection
    {
        return $this->activeAdsWithObjectives ??= $this->repository->activeAdsWithObjectives();
    }

    /**
     * The campaign's active ads, with their objectives, out of the ones already
     * loaded.
     *
     * @return list<Ad>
     */
    public function activeAdsOf(Campaign $campaign): array
    {
        return array_values(array_filter($this->activeAds(), fn (Ad $ad): bool => $ad->campaign_id === $campaign->id));
    }

    /** One ad with its objectives, read again only when it is not among the active ones. */
    public function adWithObjectives(int $id): Ad
    {
        foreach ($this->activeAds() as $ad) {
            if ($ad->id === $id) {
                return $ad;
            }
        }

        return $this->repository->adWithObjectives($id);
    }

    /**
     * @param  array<string, string>|null  $params
     */
    public function resolveAd(?array $params): ?Ad
    {
        if ($params === null || $params === []) {
            return null;
        }

        /** @var Ad|null $ad */
        $ad = $this->attribution->mostSpecific($this->repository->activeAdsWithCampaign(), $params);

        return $ad;
    }

    /**
     * @param  array<string, string>|null  $params
     */
    public function resolveCampaign(?array $params): ?Campaign
    {
        if ($params === null || $params === []) {
            return null;
        }

        /** @var Campaign|null $campaign */
        $campaign = $this->attribution->mostSpecific($this->activeCampaigns(), $params);

        return $campaign;
    }

    /**
     * Total ad-driven traffic over the period: sessions matching a defined
     * campaign (not merely any tagged session), so the headline reconciles with
     * the per-campaign figures.
     *
     * @return array{sessions: int, visitors: int|null}
     */
    public function headline(Period $period, ?string $subjectType): array
    {
        $traffic = TaggedTraffic::of($this->claimedByACampaign($period, $subjectType));

        return ['sessions' => $traffic['sessions'], 'visitors' => $traffic['visitors']];
    }

    /**
     * Campaign-matched sessions, and their distinct visitors, per day, keyed by Y-m-d.
     *
     * @return array{sessions: array<string, int>, visitors: array<string, int>|null}
     */
    public function daily(Period $period, ?string $subjectType): array
    {
        $traffic = TaggedTraffic::of($this->claimedByACampaign($period, $subjectType));

        return ['sessions' => $traffic['daily'], 'visitors' => $traffic['dailyVisitors']];
    }

    /**
     * The period's tagged groups an active campaign claims.
     *
     * @return list<TaggedGroup>
     */
    private function claimedByACampaign(Period $period, ?string $subjectType): array
    {
        return array_merge(...array_values($this->claimedBy($this->taggedGroups($period, $subjectType), $this->activeCampaigns())));
    }

    /**
     * The generic source of every ad-tagged session that matches a defined
     * campaign, so a channel breakdown can reclassify them as paid. The active
     * campaigns can be passed in to avoid re-loading them across periods.
     *
     * @param  list<Campaign>|null  $campaigns
     * @return array<int, string> session id => source
     */
    public function matchedSessionSources(Period $period, ?string $subjectType, ?array $campaigns = null): array
    {
        $sources = [];
        foreach ($this->matchingACampaign($period, $subjectType, $campaigns ?? $this->activeCampaigns()) as $session) {
            $sources[$session->id] = $session->source ?? 'direct';
        }

        return $sources;
    }

    /**
     * The period's tagged sessions that one of the given campaigns claims.
     *
     * @param  list<Campaign>  $campaigns
     * @return list<Session>
     */
    private function matchingACampaign(Period $period, ?string $subjectType, array $campaigns): array
    {
        return array_values(array_filter(
            $this->taggedSessionRows($period, $subjectType)->all(),
            fn (Session $session): bool => $this->attribution->mostSpecific($campaigns, $session->mkt_params ?? []) instanceof Campaign,
        ));
    }

    /**
     * Per-campaign and per-ad traffic, matched from the captured params in one pass.
     *
     * @return array{campaigns: array<int, array{sessions: int, visitors: int|null}>, ads: array<int, array{sessions: int, visitors: int|null}>}
     */
    public function performance(Period $period, ?string $subjectType): array
    {
        $groups = $this->taggedGroups($period, $subjectType);

        return [
            'campaigns' => TaggedTraffic::rows($this->claimedBy($groups, $this->activeCampaigns())),
            'ads' => TaggedTraffic::rows($this->claimedBy($groups, $this->activeAds())),
        ];
    }

    /**
     * One campaign's traffic over the period, its daily sessions for a trend,
     * and its per-ad breakdown, all under most-specific attribution (matching
     * the overview figures).
     *
     * @return array{sessions: int, visitors: int|null, daily: array<string, int>, dailyVisitors: array<string, int>|null, ads: array<int, array{sessions: int, visitors: int|null}>}
     */
    public function campaignReport(Period $period, ?string $subjectType, Campaign $campaign): array
    {
        $groups = $this->claimedBy($this->taggedGroups($period, $subjectType), $this->activeCampaigns())[$campaign->id] ?? [];

        return [...TaggedTraffic::of($groups), 'ads' => TaggedTraffic::rows($this->claimedBy($groups, $this->activeAdsOf($campaign)))];
    }

    /**
     * One ad's traffic over the period, its sessions and visitors per day, under
     * most-specific attribution among all ads.
     *
     * @return array{sessions: int, visitors: int|null, daily: array<string, int>, dailyVisitors: array<string, int>|null}
     */
    public function adReport(Period $period, ?string $subjectType, Ad $ad): array
    {
        return TaggedTraffic::of($this->claimedBy($this->taggedGroups($period, $subjectType), $this->activeAds())[$ad->id] ?? []);
    }

    /**
     * Conversions credited to each ad, campaign and objective over the period. A
     * conversion is an ad-driven visitor who completed one of the ad's
     * objectives (a tracked event they recorded, or a funnel they completed).
     * Counts are distinct visitors, so an ad's conversions never exceed its
     * visitors · none over a period reaching days whose sessions or named
     * events are erased.
     *
     * @return array{total: int, campaigns: array<int, int>, ads: array<int, int>, objectives: array<int, array<string, int>>, daily: array<string, int>, campaignDaily: array<int, array<string, int>>, adDaily: array<int, array<string, int>>}|null
     */
    public function conversions(Period $period, ?string $subjectType, FunnelRegistry $funnels): ?array
    {
        if (RetentionWindow::reaches($period, $this->window->eventsLine())) {
            return null;
        }

        $ads = $this->activeAdsWithObjectives();
        $visitorAds = $this->visitorAdMap($period, $subjectType, array_values($ads->all()));

        return $this->crediter->credit($ads, $visitorAds, $period, $subjectType, $funnels);
    }

    /**
     * Attribution phase: the ads each visitor was driven by, from the tagged
     * sessions of the period under most-specific matching.
     *
     * @param  list<Ad>  $ads
     * @return array<int, array<int, true>> visitor id => set of ad ids
     */
    private function visitorAdMap(Period $period, ?string $subjectType, array $ads): array
    {
        /** @var array<int, array<int, true>> $visitorAds */
        $visitorAds = [];

        foreach ($this->taggedSessionRows($period, $subjectType) as $session) {
            $ad = $this->attribution->mostSpecific($ads, $session->mkt_params ?? []);
            if ($ad instanceof Ad) {
                $visitorAds[$session->visitor_id][$ad->id] = true;
            }
        }

        return $visitorAds;
    }

    /**
     * The detailed conversion elements for a set of ads (a campaign's ads, or a
     * single ad): each objective with its conversions and, for a funnel, its
     * per-step reach. Sorted by conversions, descending · none over a period
     * reaching days whose sessions or named events are erased.
     *
     * @param  list<Ad>  $ads  active ads with their objectives loaded
     * @return list<array{type: string, reference: string, label: string, adId: int, adName: string, conversions: int, steps: list<array{label: string, count: int}>|null}>|null
     */
    public function conversionElements(Period $period, ?string $subjectType, FunnelRegistry $funnels, EventRegistry $events, array $ads): ?array
    {
        if (RetentionWindow::reaches($period, $this->window->eventsLine())) {
            return null;
        }

        if ($ads === []) {
            return [];
        }

        return $this->crediter->elements($ads, $this->adVisitors($period, $subjectType, $ads), $period, $subjectType, $funnels, $events);
    }

    /**
     * Attribution phase for a set of ads: the visitors each of them drove, from
     * the tagged sessions of the period under most-specific matching among all
     * active ads.
     *
     * @param  list<Ad>  $ads
     * @return array<int, array<int, true>> ad id => set of visitor ids
     */
    private function adVisitors(Period $period, ?string $subjectType, array $ads): array
    {
        $allAds = $this->activeAds();
        $wantedIds = array_map(fn (Ad $ad): int => $ad->id, $ads);

        /** @var array<int, array<int, true>> $adVisitors */
        $adVisitors = [];
        foreach ($this->taggedSessionRows($period, $subjectType) as $session) {
            $ad = $this->attribution->mostSpecific($allAds, $session->mkt_params ?? []);
            if ($ad instanceof Ad && in_array($ad->id, $wantedIds, true)) {
                $adVisitors[$ad->id][$session->visitor_id] = true;
            }
        }

        return $adVisitors;
    }
}
