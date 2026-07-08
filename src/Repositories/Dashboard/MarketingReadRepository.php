<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Enums\ObjectiveType;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Funnels\Funnel;
use Falcon\Analytics\Funnels\FunnelRegistry;
use Falcon\Analytics\Funnels\FunnelStep;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Repositories\Concerns\ScopesSessionQueries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read model for the marketing screens. Sessions carry the raw landing-URL
 * parameters (mkt_params); campaigns and ads are matched to them by their
 * conditions (all must hold, AND; the most specific rule wins) at report time,
 * so an ad defined after the fact still sees its full history.
 */
final class MarketingReadRepository
{
    use ScopesSessionQueries;

    /**
     * Active campaigns and ads, loaded once per instance. A dashboard render calls
     * several read methods that each need the same lists; the repository is resolved
     * once per request (not a singleton), so memoizing here avoids reloading them.
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
     * Ad-tagged sessions for a period, loaded once and reused. headline,
     * dailySessions, performance and conversions all scan the same tagged sessions
     * to match them to campaigns/ads; fetching a superset of the columns they need
     * once (per period + subject) avoids repeating that query on every render.
     *
     * @return Collection<int, Session>
     */
    private function taggedSessionRows(Period $period, ?string $subjectType): Collection
    {
        $key = $period->from->format('c').'|'.$period->to->format('c').'|'.($subjectType ?? '');

        return $this->taggedSessionCache[$key] ??= $this->taggedSessions($period, $subjectType)
            ->get(['id', 'visitor_id', 'source', 'mkt_params', 'started_at']);
    }

    /**
     * @return list<Campaign>
     */
    private function activeCampaigns(): array
    {
        return $this->activeCampaigns ??= Campaign::query()->where('is_active', true)->get()->all();
    }

    /**
     * @return list<Ad>
     */
    private function activeAds(): array
    {
        return $this->activeAds ??= $this->activeAdsWithObjectives()->all();
    }

    /**
     * @return Collection<int, Ad>
     */
    private function activeAdsWithObjectives(): Collection
    {
        return $this->activeAdsWithObjectives ??= Ad::query()->where('is_active', true)->with('objectives')->get();
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
        $ad = $this->mostSpecific(Ad::query()->where('is_active', true)->with('campaign')->get()->all(), $params);

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
        $campaign = $this->mostSpecific($this->activeCampaigns(), $params);

        return $campaign;
    }

    /**
     * Total ad-driven traffic over the period: sessions matching a defined campaign
     * (not merely any tagged session), so the headline reconciles with the
     * per-campaign figures.
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
            if ($this->mostSpecific($campaigns, $session->mkt_params ?? []) instanceof Campaign) {
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
            if ($this->mostSpecific($campaigns, $session->mkt_params ?? []) instanceof Campaign) {
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
            if ($this->mostSpecific($campaigns, $session->mkt_params ?? []) instanceof Campaign) {
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

            $campaign = $this->mostSpecific($campaigns, $params);
            if ($campaign instanceof Campaign) {
                $campaignSessions[$campaign->id] = ($campaignSessions[$campaign->id] ?? 0) + 1;
                $campaignVisitors[$campaign->id][$visitor] = true;
            }

            $ad = $this->mostSpecific($ads, $params);
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
     * One campaign's traffic over the period, its daily sessions for a trend, and
     * its per-ad breakdown, all under most-specific attribution (matching the
     * overview figures).
     *
     * @return array{sessions: int, visitors: int, daily: array<string, int>, ads: array<int, array{sessions: int, visitors: int}>}
     */
    public function campaignReport(Period $period, ?string $subjectType, Campaign $campaign): array
    {
        $campaigns = $this->activeCampaigns();
        $ads = $campaign->ads()->where('is_active', true)->get()->all();

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

            $bestCampaign = $this->mostSpecific($campaigns, $params);
            if (! ($bestCampaign instanceof Campaign) || $bestCampaign->id !== $campaign->id) {
                continue;
            }

            $visitor = (int) $session->visitor_id;
            $sessions++;
            $visitors[$visitor] = true;
            $daily[$session->started_at->toDateString()] = ($daily[$session->started_at->toDateString()] ?? 0) + 1;

            $bestAd = $this->mostSpecific($ads, $params);
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
     * One ad's traffic over the period and its daily sessions, under most-specific
     * attribution among all ads.
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
            $bestAd = $this->mostSpecific($ads, $session->mkt_params ?? []);
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
     * conversion is an ad-driven visitor who completed one of the ad's objectives
     * (a tracked event they recorded, or a funnel they completed). Counts are
     * distinct visitors, so an ad's conversions never exceed its visitors.
     *
     * @return array{total: int, campaigns: array<int, int>, ads: array<int, int>, objectives: array<int, array<string, int>>, daily: array<string, int>, campaignDaily: array<int, array<string, int>>, adDaily: array<int, array<string, int>>}
     */
    public function conversions(Period $period, ?string $subjectType, FunnelRegistry $funnels): array
    {
        $ads = $this->activeAdsWithObjectives();
        $adList = $ads->all();
        $adById = $ads->keyBy('id');

        /** @var array<int, array<int, true>> $visitorAds */
        $visitorAds = [];
        foreach ($this->taggedSessionRows($period, $subjectType) as $session) {
            $ad = $this->mostSpecific($adList, $session->mkt_params ?? []);
            if ($ad instanceof Ad) {
                $visitorAds[(int) $session->visitor_id][$ad->id] = true;
            }
        }

        if ($visitorAds === []) {
            return ['total' => 0, 'campaigns' => [], 'ads' => [], 'objectives' => [], 'daily' => [], 'campaignDaily' => [], 'adDaily' => []];
        }

        $visitorIds = array_keys($visitorAds);

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

        /** @var array<string, array<int, string>> $eventCompleters */
        $eventCompleters = [];
        if ($eventNames !== []) {
            $rows = Event::query()
                ->whereIn('visitor_id', $visitorIds)
                ->whereIn('name', array_keys($eventNames))
                ->whereBetween('occurred_at', [$period->from, $period->to])
                ->whereHas('session', fn (Builder $session): Builder => $session->where('is_bot', false))
                ->get(['visitor_id', 'name', 'occurred_at']);

            foreach ($rows as $row) {
                $name = (string) $row->name;
                $visitorId = (int) $row->visitor_id;
                $day = $row->occurred_at->toDateString();
                if (! isset($eventCompleters[$name][$visitorId]) || $day < $eventCompleters[$name][$visitorId]) {
                    $eventCompleters[$name][$visitorId] = $day;
                }
            }
        }

        /** @var array<string, array<int, string>> $funnelCompleters */
        $funnelCompleters = [];
        foreach (array_keys($funnelKeys) as $key) {
            $funnel = $funnels->get($key);
            if ($funnel instanceof Funnel) {
                $funnelCompleters[$key] = $this->funnelCompleters($funnel, $period, $subjectType, $visitorIds);
            }
        }

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
     * The subset of the given visitors who completed the funnel (reached its last
     * step in chronological order) over the period, mirroring the funnels screen.
     *
     * @param  list<int>  $visitorIds
     * @return array<int, string> visitor id => completion day (Y-m-d)
     */
    private function funnelCompleters(Funnel $funnel, Period $period, ?string $subjectType, array $visitorIds): array
    {
        $steps = $funnel->steps();
        $stepCount = count($steps);

        if ($stepCount === 0 || $visitorIds === []) {
            return [];
        }

        $names = [];
        $routes = [];
        foreach ($steps as $step) {
            if ($step->event !== null) {
                $names[] = $step->event;
            }
            if ($step->route !== null) {
                $routes[] = $step->route;
            }
        }

        $query = Event::query()
            ->select(['id', 'visitor_id', 'type', 'name', 'route', 'occurred_at'])
            ->whereIn('visitor_id', $visitorIds)
            ->whereBetween('occurred_at', [$period->from, $period->to])
            ->whereHas('session', fn (Builder $session): Builder => $session->where('is_bot', false))
            ->where(function (Builder $matcher) use ($names, $routes): void {
                if ($names !== []) {
                    $matcher->whereIn('name', $names);
                }
                if ($routes !== []) {
                    $matcher->orWhere(function (Builder $inner) use ($routes): void {
                        $inner->where('type', EventType::Pageview)->whereIn('route', $routes);
                    });
                }
            })
            ->when($subjectType !== null, fn (Builder $q): Builder => $q->whereHas(
                'visitor',
                fn (Builder $visitor): Builder => $visitor->where('subject_type', $subjectType),
            ))
            ->orderBy('visitor_id')
            ->orderBy('occurred_at')
            ->orderBy('id');

        /** @var array<int, int> $pointer */
        $pointer = [];
        /** @var array<int, string> $completers */
        $completers = [];

        foreach ($query->cursor() as $event) {
            $visitorId = (int) $event->visitor_id;

            if (isset($completers[$visitorId])) {
                continue;
            }

            $position = $pointer[$visitorId] ?? 0;

            if ($position < $stepCount && $this->stepMatches($steps[$position], $event)) {
                $position++;
                $pointer[$visitorId] = $position;

                if ($position === $stepCount) {
                    $completers[$visitorId] = $event->occurred_at->toDateString();
                }
            }
        }

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
            $ad = $this->mostSpecific($allAds, $session->mkt_params ?? []);
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

        $elements = [];
        foreach ($ads as $ad) {
            $visitorIds = array_keys($adVisitors[$ad->id] ?? []);

            foreach ($ad->objectives as $objective) {
                if ($objective->type === ObjectiveType::Event) {
                    $count = $visitorIds === [] ? 0 : Event::query()
                        ->whereIn('visitor_id', $visitorIds)
                        ->where('name', $objective->reference)
                        ->whereBetween('occurred_at', [$period->from, $period->to])
                        ->whereHas('session', fn (Builder $session): Builder => $session->where('is_bot', false))
                        ->distinct()
                        ->count('visitor_id');

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
     * How many of the given visitors reached each funnel step (monotonically
     * decreasing), over the period. Index i = visitors who reached step i.
     *
     * @param  list<int>  $visitorIds
     * @return list<int>
     */
    private function funnelStepReach(Funnel $funnel, Period $period, ?string $subjectType, array $visitorIds): array
    {
        $steps = $funnel->steps();
        $stepCount = count($steps);
        $reached = array_fill(0, $stepCount, 0);

        if ($stepCount === 0 || $visitorIds === []) {
            return $reached;
        }

        $names = [];
        $routes = [];
        foreach ($steps as $step) {
            if ($step->event !== null) {
                $names[] = $step->event;
            }
            if ($step->route !== null) {
                $routes[] = $step->route;
            }
        }

        $query = Event::query()
            ->select(['id', 'visitor_id', 'type', 'name', 'route', 'occurred_at'])
            ->whereIn('visitor_id', $visitorIds)
            ->whereBetween('occurred_at', [$period->from, $period->to])
            ->whereHas('session', fn (Builder $session): Builder => $session->where('is_bot', false))
            ->where(function (Builder $matcher) use ($names, $routes): void {
                if ($names !== []) {
                    $matcher->whereIn('name', $names);
                }
                if ($routes !== []) {
                    $matcher->orWhere(function (Builder $inner) use ($routes): void {
                        $inner->where('type', EventType::Pageview)->whereIn('route', $routes);
                    });
                }
            })
            ->when($subjectType !== null, fn (Builder $q): Builder => $q->whereHas(
                'visitor',
                fn (Builder $visitor): Builder => $visitor->where('subject_type', $subjectType),
            ))
            ->orderBy('visitor_id')
            ->orderBy('occurred_at')
            ->orderBy('id');

        /** @var array<int, int> $pointer */
        $pointer = [];

        foreach ($query->cursor() as $event) {
            $visitorId = (int) $event->visitor_id;
            $position = $pointer[$visitorId] ?? 0;

            if ($position < $stepCount && $this->stepMatches($steps[$position], $event)) {
                $reached[$position]++;
                $pointer[$visitorId] = $position + 1;
            }
        }

        return $reached;
    }

    private function stepMatches(FunnelStep $step, Event $event): bool
    {
        if ($step->event !== null) {
            return $event->name === $step->event;
        }

        return $event->type === EventType::Pageview && $event->route === $step->route;
    }

    /**
     * @return Builder<Session>
     */
    private function taggedSessions(Period $period, ?string $subjectType): Builder
    {
        return Session::query()
            ->where('is_bot', false)
            ->whereNotNull('mkt_params')
            ->whereBetween('started_at', [$period->from, $period->to])
            ->when($subjectType !== null, fn (Builder $query): Builder => $query->where('subject_type', $subjectType));
    }

    /**
     * @param  list<Campaign>|list<Ad>  $candidates
     * @param  array<string, string>  $params
     */
    private function mostSpecific(array $candidates, array $params): Campaign|Ad|null
    {
        $best = null;
        $bestSpecificity = 0;

        foreach ($candidates as $candidate) {
            $conditions = $candidate->match_conditions ?? [];

            if ($conditions === [] || ! $this->matches($conditions, $params)) {
                continue;
            }

            if (count($conditions) > $bestSpecificity) {
                $best = $candidate;
                $bestSpecificity = count($conditions);
            }
        }

        return $best;
    }

    /**
     * @param  array<int, array{param: string, value: string}>  $conditions
     * @param  array<string, string>  $params
     */
    private function matches(array $conditions, array $params): bool
    {
        foreach ($conditions as $condition) {
            if (($params[$condition['param']] ?? null) !== $condition['value']) {
                return false;
            }
        }

        return true;
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
