<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\DTOs\Dashboard\TaggedSessions;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Repositories\Dashboard\MarketingReadRepository;
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

    /**
     * The collaborator defaults so a plain `new MarketingReportBuilder` still
     * works (tests, ad-hoc use); the container injects the shared services
     * otherwise.
     */
    public function __construct(
        private MarketingReadRepository $repository = new MarketingReadRepository,
        private AttributionResolver $attribution = new AttributionResolver,
        private MarketingConversionCrediter $crediter = new MarketingConversionCrediter,
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
     * The ceiling, when the sessions this builder served for that period were
     * cut at it, or null when they were whole or never read. A screen asks it
     * after building its figures, so nothing is read for the answer.
     */
    public function truncatedAt(Period $period, ?string $subjectType): ?int
    {
        $read = $this->taggedSessionCache[self::cacheKey($period, $subjectType)] ?? null;

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
     * @return array{sessions: int, visitors: int}
     */
    public function headline(Period $period, ?string $subjectType): array
    {
        $traffic = $this->traffic($this->matchingACampaign($period, $subjectType, $this->activeCampaigns()));

        return ['sessions' => $traffic['sessions'], 'visitors' => $traffic['visitors']];
    }

    /**
     * Campaign-matched sessions per day, keyed by Y-m-d.
     *
     * @return array<string, int>
     */
    public function dailySessions(Period $period, ?string $subjectType): array
    {
        return $this->traffic($this->matchingACampaign($period, $subjectType, $this->activeCampaigns()))['daily'];
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
     * @return array{campaigns: array<int, array{sessions: int, visitors: int}>, ads: array<int, array{sessions: int, visitors: int}>}
     */
    public function performance(Period $period, ?string $subjectType): array
    {
        $campaigns = $this->activeCampaigns();
        $ads = $this->activeAds();

        /** @var array<int, int> $campaignSessions */
        $campaignSessions = [];
        /** @var array<int, array<int, true>> $campaignVisitors */
        $campaignVisitors = [];
        /** @var array<int, int> $adSessions */
        $adSessions = [];
        /** @var array<int, array<int, true>> $adVisitors */
        $adVisitors = [];

        foreach ($this->taggedSessionRows($period, $subjectType) as $session) {
            $params = $session->mkt_params ?? [];
            $visitor = $session->visitor_id;

            $campaign = $this->attribution->mostSpecific($campaigns, $params);
            if ($campaign instanceof Campaign) {
                $campaignSessions[$campaign->id] = ($campaignSessions[$campaign->id] ?? 0) + 1;
                $campaignVisitors[$campaign->id][$visitor] = true;
            }

            $ad = $this->attribution->mostSpecific($ads, $params);
            if ($ad instanceof Ad) {
                $adSessions[$ad->id] = ($adSessions[$ad->id] ?? 0) + 1;
                $adVisitors[$ad->id][$visitor] = true;
            }
        }

        return [
            'campaigns' => $this->buildRows($campaignSessions, $campaignVisitors),
            'ads' => $this->buildRows($adSessions, $adVisitors),
        ];
    }

    /**
     * One campaign's traffic over the period, its daily sessions for a trend,
     * and its per-ad breakdown, all under most-specific attribution (matching
     * the overview figures).
     *
     * @return array{sessions: int, visitors: int, daily: array<string, int>, ads: array<int, array{sessions: int, visitors: int}>}
     */
    public function campaignReport(Period $period, ?string $subjectType, Campaign $campaign): array
    {
        $campaigns = $this->activeCampaigns();
        $sessions = array_values(array_filter(
            $this->taggedSessionRows($period, $subjectType)->all(),
            fn (Session $session): bool => $this->attribution->mostSpecific($campaigns, $session->mkt_params ?? [])?->id === $campaign->id,
        ));

        $ads = $this->activeAdsOf($campaign);
        /** @var array<int, int> $adSessions */
        $adSessions = [];
        /** @var array<int, array<int, true>> $adVisitors */
        $adVisitors = [];

        foreach ($sessions as $session) {
            $bestAd = $this->attribution->mostSpecific($ads, $session->mkt_params ?? []);
            if ($bestAd instanceof Ad) {
                $adSessions[$bestAd->id] = ($adSessions[$bestAd->id] ?? 0) + 1;
                $adVisitors[$bestAd->id][$session->visitor_id] = true;
            }
        }

        return [...$this->traffic($sessions), 'ads' => $this->buildRows($adSessions, $adVisitors)];
    }

    /**
     * One ad's traffic over the period and its daily sessions, under
     * most-specific attribution among all ads.
     *
     * @return array{sessions: int, visitors: int, daily: array<string, int>}
     */
    public function adReport(Period $period, ?string $subjectType, Ad $ad): array
    {
        $ads = $this->activeAds();

        return $this->traffic(array_values(array_filter(
            $this->taggedSessionRows($period, $subjectType)->all(),
            fn (Session $session): bool => $this->attribution->mostSpecific($ads, $session->mkt_params ?? [])?->id === $ad->id,
        )));
    }

    /**
     * How many sessions, how many distinct visitors, and how many sessions per
     * day, keyed by Y-m-d.
     *
     * @param  list<Session>  $sessions
     * @return array{sessions: int, visitors: int, daily: array<string, int>}
     */
    private function traffic(array $sessions): array
    {
        /** @var array<int, true> $visitors */
        $visitors = [];
        /** @var array<string, int> $daily */
        $daily = [];

        foreach ($sessions as $session) {
            $visitors[$session->visitor_id] = true;
            $daily[$session->started_at->toDateString()] = ($daily[$session->started_at->toDateString()] ?? 0) + 1;
        }

        return ['sessions' => count($sessions), 'visitors' => count($visitors), 'daily' => $daily];
    }

    /**
     * Conversions credited to each ad, campaign and objective over the period. A
     * conversion is an ad-driven visitor who completed one of the ad's
     * objectives (a tracked event they recorded, or a funnel they completed).
     * Counts are distinct visitors, so an ad's conversions never exceed its
     * visitors.
     *
     * @return array{total: int, campaigns: array<int, int>, ads: array<int, int>, objectives: array<int, array<string, int>>, daily: array<string, int>, campaignDaily: array<int, array<string, int>>, adDaily: array<int, array<string, int>>}
     */
    public function conversions(Period $period, ?string $subjectType, FunnelRegistry $funnels): array
    {
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
     * per-step reach. Sorted by conversions, descending.
     *
     * @param  list<Ad>  $ads  active ads with their objectives loaded
     * @return list<array{type: string, reference: string, label: string, adId: int, adName: string, conversions: int, steps: list<array{label: string, count: int}>|null}>
     */
    public function conversionElements(Period $period, ?string $subjectType, FunnelRegistry $funnels, EventRegistry $events, array $ads): array
    {
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

    /**
     * @param  array<int, int>  $sessions
     * @param  array<int, array<int, true>>  $visitors
     * @return array<int, array{sessions: int, visitors: int}>
     */
    private function buildRows(array $sessions, array $visitors): array
    {
        $rows = [];

        foreach ($sessions as $id => $count) {
            $rows[$id] = ['sessions' => $count, 'visitors' => count($visitors[$id] ?? [])];
        }

        return $rows;
    }
}
