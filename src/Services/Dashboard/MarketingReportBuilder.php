<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Enums\ObjectiveType;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\Funnel;
use Falcon\Analytics\Funnels\FunnelEventWalker;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Repositories\Dashboard\MarketingReadRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * Builds the marketing reports from the repository's raw rows. Sessions carry
 * the raw landing-URL parameters (mkt_params); campaigns and ads are matched to
 * them by their conditions (all must hold, AND; the most specific rule wins) at
 * report time, so an ad defined after the fact still sees its full history.
 * Attribution, the crediting of conversions per objective and every aggregate
 * (headline, daily trend, performance, campaign/ad reports) live here; the
 * repository only reads.
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

    /** @var array<string, Collection<int, Session>> */
    private array $taggedSessionCache = [];

    /**
     * The collaborator defaults so a plain `new MarketingReportBuilder` still
     * works (tests, ad-hoc use); the container injects the shared services
     * otherwise.
     */
    public function __construct(
        private MarketingReadRepository $repository = new MarketingReadRepository,
        private AttributionResolver $attribution = new AttributionResolver,
        private FunnelEventWalker $walker = new FunnelEventWalker,
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
        $key = $period->from->format('c').'|'.$period->to->format('c').'|'.($subjectType ?? '');

        return $this->taggedSessionCache[$key] ??= $this->repository->taggedSessionRows($period, $subjectType);
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
        // `array_values` · une collection Eloquent est deja indexee depuis zero,
        // mais son type ne le dit pas et les appelants attendent une liste.
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
        $campaigns = $this->activeCampaigns();

        $sessions = 0;
        /** @var array<int, true> $visitors */
        $visitors = [];

        foreach ($this->taggedSessionRows($period, $subjectType) as $session) {
            if ($this->attribution->mostSpecific($campaigns, $session->mkt_params ?? []) instanceof Campaign) {
                $sessions++;
                $visitors[(int) $session->visitor_id] = true;
            }
        }

        return ['sessions' => $sessions, 'visitors' => count($visitors)];
    }

    /**
     * Campaign-matched sessions per day, keyed by Y-m-d.
     *
     * @return array<string, int>
     */
    public function dailySessions(Period $period, ?string $subjectType): array
    {
        $campaigns = $this->activeCampaigns();

        /** @var array<string, int> $daily */
        $daily = [];

        foreach ($this->taggedSessionRows($period, $subjectType) as $session) {
            if ($this->attribution->mostSpecific($campaigns, $session->mkt_params ?? []) instanceof Campaign) {
                $day = $session->started_at->toDateString();
                $daily[$day] = ($daily[$day] ?? 0) + 1;
            }
        }

        return $daily;
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
        $campaigns ??= $this->activeCampaigns();

        $sources = [];
        foreach ($this->taggedSessionRows($period, $subjectType) as $session) {
            if ($this->attribution->mostSpecific($campaigns, $session->mkt_params ?? []) instanceof Campaign) {
                $sources[(int) $session->id] = (string) ($session->source ?? 'direct');
            }
        }

        return $sources;
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
            $visitor = (int) $session->visitor_id;

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
        $ads = $this->repository->activeCampaignAds($campaign);

        $sessions = 0;
        /** @var array<int, true> $visitors */
        $visitors = [];
        /** @var array<string, int> $daily */
        $daily = [];
        /** @var array<int, int> $adSessions */
        $adSessions = [];
        /** @var array<int, array<int, true>> $adVisitors */
        $adVisitors = [];

        foreach ($this->taggedSessionRows($period, $subjectType) as $session) {
            $params = $session->mkt_params ?? [];

            $bestCampaign = $this->attribution->mostSpecific($campaigns, $params);
            if (! ($bestCampaign instanceof Campaign) || $bestCampaign->id !== $campaign->id) {
                continue;
            }

            $visitor = (int) $session->visitor_id;
            $sessions++;
            $visitors[$visitor] = true;
            $daily[$session->started_at->toDateString()] = ($daily[$session->started_at->toDateString()] ?? 0) + 1;

            $bestAd = $this->attribution->mostSpecific($ads, $params);
            if ($bestAd instanceof Ad) {
                $adSessions[$bestAd->id] = ($adSessions[$bestAd->id] ?? 0) + 1;
                $adVisitors[$bestAd->id][$visitor] = true;
            }
        }

        return [
            'sessions' => $sessions,
            'visitors' => count($visitors),
            'daily' => $daily,
            'ads' => $this->buildRows($adSessions, $adVisitors),
        ];
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

        $sessions = 0;
        /** @var array<int, true> $visitors */
        $visitors = [];
        /** @var array<string, int> $daily */
        $daily = [];

        foreach ($this->taggedSessionRows($period, $subjectType) as $session) {
            $bestAd = $this->attribution->mostSpecific($ads, $session->mkt_params ?? []);
            if (! ($bestAd instanceof Ad) || $bestAd->id !== $ad->id) {
                continue;
            }

            $sessions++;
            $visitors[(int) $session->visitor_id] = true;
            $daily[$session->started_at->toDateString()] = ($daily[$session->started_at->toDateString()] ?? 0) + 1;
        }

        return ['sessions' => $sessions, 'visitors' => count($visitors), 'daily' => $daily];
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

        if ($visitorAds === []) {
            return ['total' => 0, 'campaigns' => [], 'ads' => [], 'objectives' => [], 'daily' => [], 'campaignDaily' => [], 'adDaily' => []];
        }

        $visitorIds = array_keys($visitorAds);
        ['events' => $eventNames, 'funnels' => $funnelKeys] = $this->objectiveReferences($ads);

        return $this->creditConversions(
            $ads->keyBy('id'),
            $visitorAds,
            $this->eventCompletionDays($visitorIds, $eventNames, $period),
            $this->funnelCompletionDays($funnelKeys, $funnels, $period, $subjectType, $visitorIds),
        );
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
                $visitorAds[(int) $session->visitor_id][$ad->id] = true;
            }
        }

        return $visitorAds;
    }

    /**
     * The distinct event names and funnel keys referenced by the ads' objectives.
     *
     * @param  Collection<int, Ad>  $ads
     * @return array{events: list<string>, funnels: list<string>}
     */
    private function objectiveReferences(Collection $ads): array
    {
        /** @var array<string, true> $eventNames */
        $eventNames = [];
        /** @var array<string, true> $funnelKeys */
        $funnelKeys = [];

        foreach ($ads as $ad) {
            foreach ($ad->objectives as $objective) {
                if ($objective->type === ObjectiveType::Event) {
                    $eventNames[$objective->reference] = true;
                } else {
                    $funnelKeys[$objective->reference] = true;
                }
            }
        }

        return ['events' => array_keys($eventNames), 'funnels' => array_keys($funnelKeys)];
    }

    /**
     * Event-objective phase: the earliest completion day of each referenced
     * event per visitor, from one batched read.
     *
     * @param  list<int>  $visitorIds
     * @param  list<string>  $names
     * @return array<string, array<int, string>> event name => visitor id => day (Y-m-d)
     */
    private function eventCompletionDays(array $visitorIds, array $names, Period $period): array
    {
        if ($names === []) {
            return [];
        }

        /** @var array<string, array<int, string>> $completers */
        $completers = [];

        foreach ($this->repository->objectiveEventRows($visitorIds, $names, $period) as $row) {
            $name = (string) $row->name;
            $visitorId = (int) $row->visitor_id;
            $day = $row->occurred_at->toDateString();
            if (! isset($completers[$name][$visitorId]) || $day < $completers[$name][$visitorId]) {
                $completers[$name][$visitorId] = $day;
            }
        }

        return $completers;
    }

    /**
     * Funnel-objective phase: the completion day of each referenced funnel per
     * visitor, walking each funnel once over the given visitors.
     *
     * @param  list<string>  $keys
     * @param  list<int>  $visitorIds
     * @return array<string, array<int, string>> funnel key => visitor id => day (Y-m-d)
     */
    private function funnelCompletionDays(array $keys, FunnelRegistry $funnels, Period $period, ?string $subjectType, array $visitorIds): array
    {
        /** @var array<string, array<int, string>> $completers */
        $completers = [];

        foreach ($keys as $key) {
            $funnel = $funnels->get($key);
            if ($funnel instanceof Funnel) {
                $completers[$key] = $this->funnelCompleters($funnel, $period, $subjectType, $visitorIds);
            }
        }

        return $completers;
    }

    /**
     * Crediting phase: mark each ad-driven visitor who completed one of the
     * ad's objectives as a converter for that ad, its campaign and the
     * objective, keeping the earliest completion day at every level for the
     * daily series.
     *
     * @param  Collection<int, Ad>  $adById
     * @param  array<int, array<int, true>>  $visitorAds
     * @param  array<string, array<int, string>>  $eventCompleters
     * @param  array<string, array<int, string>>  $funnelCompleters
     * @return array{total: int, campaigns: array<int, int>, ads: array<int, int>, objectives: array<int, array<string, int>>, daily: array<string, int>, campaignDaily: array<int, array<string, int>>, adDaily: array<int, array<string, int>>}
     */
    private function creditConversions(Collection $adById, array $visitorAds, array $eventCompleters, array $funnelCompleters): array
    {
        /** @var array<int, array<int, true>> $adConverters */
        $adConverters = [];
        /** @var array<int, array<int, true>> $campaignConverters */
        $campaignConverters = [];
        /** @var array<int, array<string, array<int, true>>> $objectiveConverters */
        $objectiveConverters = [];
        /** @var array<int, true> $totalConverters */
        $totalConverters = [];
        /** @var array<int, string> $visitorDay earliest conversion day per visitor, any ad */
        $visitorDay = [];
        /** @var array<int, array<int, string>> $adVisitorDay */
        $adVisitorDay = [];
        /** @var array<int, array<int, string>> $campaignVisitorDay */
        $campaignVisitorDay = [];

        foreach ($visitorAds as $visitorId => $adIds) {
            foreach (array_keys($adIds) as $adId) {
                $ad = $adById->get($adId);
                if (! ($ad instanceof Ad)) {
                    continue;
                }

                $day = null;
                foreach ($ad->objectives as $objective) {
                    $completedOn = $objective->type === ObjectiveType::Event
                        ? ($eventCompleters[$objective->reference][$visitorId] ?? null)
                        : ($funnelCompleters[$objective->reference][$visitorId] ?? null);

                    if ($completedOn !== null) {
                        $objectiveConverters[$adId][$objective->reference][$visitorId] = true;
                        if ($day === null || $completedOn < $day) {
                            $day = $completedOn;
                        }
                    }
                }

                if ($day !== null) {
                    $adConverters[$adId][$visitorId] = true;
                    $campaignConverters[$ad->campaign_id][$visitorId] = true;
                    $totalConverters[$visitorId] = true;
                    $adVisitorDay[$adId][$visitorId] = $day;

                    if (! isset($campaignVisitorDay[$ad->campaign_id][$visitorId]) || $day < $campaignVisitorDay[$ad->campaign_id][$visitorId]) {
                        $campaignVisitorDay[$ad->campaign_id][$visitorId] = $day;
                    }
                    if (! isset($visitorDay[$visitorId]) || $day < $visitorDay[$visitorId]) {
                        $visitorDay[$visitorId] = $day;
                    }
                }
            }
        }

        return [
            'total' => count($totalConverters),
            'campaigns' => array_map(fn (array $visitors): int => count($visitors), $campaignConverters),
            'ads' => array_map(fn (array $visitors): int => count($visitors), $adConverters),
            'objectives' => array_map(
                fn (array $refs): array => array_map(fn (array $visitors): int => count($visitors), $refs),
                $objectiveConverters,
            ),
            'daily' => $this->dailyFromDays($visitorDay),
            'campaignDaily' => array_map(fn (array $days): array => $this->dailyFromDays($days), $campaignVisitorDay),
            'adDaily' => array_map(fn (array $days): array => $this->dailyFromDays($days), $adVisitorDay),
        ];
    }

    /**
     * Counts of converting visitors per day.
     *
     * @param  array<int, string>  $daysByVisitor
     * @return array<string, int>
     */
    private function dailyFromDays(array $daysByVisitor): array
    {
        $daily = [];
        foreach ($daysByVisitor as $day) {
            $daily[$day] = ($daily[$day] ?? 0) + 1;
        }

        return $daily;
    }

    /**
     * The subset of the given visitors who completed the funnel (reached its
     * last step in chronological order) over the period, mirroring the funnels
     * screen.
     *
     * @param  list<int>  $visitorIds
     * @return array<int, string> visitor id => completion day (Y-m-d)
     */
    private function funnelCompleters(Funnel $funnel, Period $period, ?string $subjectType, array $visitorIds): array
    {
        $lastIndex = count($funnel->steps()) - 1;

        /** @var array<int, string> $completers */
        $completers = [];

        $this->walker->walk(
            $funnel,
            $period,
            $subjectType,
            $visitorIds,
            function (int $visitorId, int $stepIndex, Event $event) use (&$completers, $lastIndex): void {
                if ($stepIndex === $lastIndex) {
                    $completers[$visitorId] = $event->occurred_at->toDateString();
                }
            },
        );

        return $completers;
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

        $allAds = $this->activeAds();
        $wantedIds = array_map(fn (Ad $ad): int => $ad->id, $ads);

        /** @var array<int, array<int, true>> $adVisitors */
        $adVisitors = [];
        foreach ($this->taggedSessionRows($period, $subjectType) as $session) {
            $ad = $this->attribution->mostSpecific($allAds, $session->mkt_params ?? []);
            if ($ad instanceof Ad && in_array($ad->id, $wantedIds, true)) {
                $adVisitors[$ad->id][(int) $session->visitor_id] = true;
            }
        }

        $eventLabels = [];
        foreach ($events->all() as $event) {
            $eventLabels[$event->name] = $event->label;
        }
        $funnelLabels = [];
        foreach ($funnels->all() as $funnel) {
            $funnelLabels[$funnel->key] = $funnel->label;
        }

        $eventVisitors = $this->objectiveEventVisitors($period, $ads, $adVisitors);

        $elements = [];
        foreach ($ads as $ad) {
            $visitorIds = array_keys($adVisitors[$ad->id] ?? []);

            foreach ($ad->objectives as $objective) {
                if ($objective->type === ObjectiveType::Event) {
                    $refVisitors = $eventVisitors[$objective->reference] ?? [];
                    $count = 0;
                    foreach ($visitorIds as $visitorId) {
                        if (isset($refVisitors[$visitorId])) {
                            $count++;
                        }
                    }

                    $elements[] = [
                        'type' => 'event',
                        'reference' => $objective->reference,
                        'label' => $eventLabels[$objective->reference] ?? $objective->reference,
                        'adId' => $ad->id,
                        'adName' => $ad->name,
                        'conversions' => $count,
                        'steps' => null,
                    ];

                    continue;
                }

                $funnel = $funnels->get($objective->reference);
                if (! ($funnel instanceof Funnel)) {
                    continue;
                }

                $reach = $this->funnelStepReach($funnel, $period, $subjectType, $visitorIds);
                $steps = [];
                foreach ($funnel->steps() as $index => $step) {
                    $steps[] = ['label' => $step->label, 'count' => $reach[$index] ?? 0];
                }

                $elements[] = [
                    'type' => 'funnel',
                    'reference' => $objective->reference,
                    'label' => $funnelLabels[$objective->reference] ?? $objective->reference,
                    'adId' => $ad->id,
                    'adName' => $ad->name,
                    'conversions' => $reach === [] ? 0 : (int) end($reach),
                    'steps' => $steps,
                ];
            }
        }

        usort($elements, fn (array $a, array $b): int => $b['conversions'] <=> $a['conversions']);

        return $elements;
    }

    /**
     * Batch every event-objective completion into one read: the visitors who
     * fired each referenced event, instead of a count query per ad per
     * objective.
     *
     * @param  list<Ad>  $ads
     * @param  array<int, array<int, true>>  $adVisitors
     * @return array<string, array<int, true>> event name => set of visitor ids
     */
    private function objectiveEventVisitors(Period $period, array $ads, array $adVisitors): array
    {
        /** @var array<string, true> $eventRefs */
        $eventRefs = [];
        foreach ($ads as $ad) {
            foreach ($ad->objectives as $objective) {
                if ($objective->type === ObjectiveType::Event) {
                    $eventRefs[$objective->reference] = true;
                }
            }
        }

        /** @var array<int, true> $allVisitorIds */
        $allVisitorIds = [];
        foreach ($adVisitors as $visitors) {
            foreach (array_keys($visitors) as $visitorId) {
                $allVisitorIds[$visitorId] = true;
            }
        }

        if ($eventRefs === [] || $allVisitorIds === []) {
            return [];
        }

        /** @var array<string, array<int, true>> $eventVisitors */
        $eventVisitors = [];
        foreach ($this->repository->distinctObjectiveEventRows(array_keys($allVisitorIds), array_keys($eventRefs), $period) as $row) {
            $eventVisitors[(string) $row->name][(int) $row->visitor_id] = true;
        }

        return $eventVisitors;
    }

    /**
     * How many of the given visitors reached each funnel step (monotonically
     * decreasing), over the period. Index i = visitors who reached step i.
     *
     * @param  list<int>  $visitorIds
     * @return list<int>
     */
    private function funnelStepReach(Funnel $funnel, Period $period, ?string $subjectType, array $visitorIds): array
    {
        $reached = array_fill(0, count($funnel->steps()), 0);

        $this->walker->walk(
            $funnel,
            $period,
            $subjectType,
            $visitorIds,
            function (int $visitorId, int $stepIndex, Event $event) use (&$reached): void {
                $reached[$stepIndex]++;
            },
        );

        // `array_values` · `array_fill` depuis zero donne bien une liste, mais
        // le compteur incremente par reference en fait perdre la trace.
        return array_values($reached);
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
