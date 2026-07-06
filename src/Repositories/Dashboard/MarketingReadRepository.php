<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Enums\ObjectiveType;
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
        $campaign = $this->mostSpecific(Campaign::query()->where('is_active', true)->get()->all(), $params);

        return $campaign;
    }

    /**
     * Total ad-driven traffic over the period.
     *
     * @return array{sessions: int, visitors: int}
     */
    public function headline(Period $period, ?string $subjectType): array
    {
        $base = $this->taggedSessions($period, $subjectType);

        return [
            'sessions' => (clone $base)->count(),
            'visitors' => (clone $base)->distinct()->count('visitor_id'),
        ];
    }

    /**
     * Ad-driven sessions per day, keyed by Y-m-d.
     *
     * @return array<string, int>
     */
    public function dailySessions(Period $period, ?string $subjectType): array
    {
        $day = $this->dayExpression('started_at');

        return $this->taggedSessions($period, $subjectType)
            ->selectRaw("{$day} as day, COUNT(*) as total")
            ->groupBy('day')
            ->get()
            ->mapWithKeys(fn (Session $row): array => [(string) $row->getAttribute('day') => (int) $row->getAttribute('total')])
            ->all();
    }

    /**
     * Per-campaign and per-ad traffic, matched from the captured params in one pass.
     *
     * @return array{campaigns: array<int, array{sessions: int, visitors: int}>, ads: array<int, array{sessions: int, visitors: int}>}
     */
    public function performance(Period $period, ?string $subjectType): array
    {
        $campaigns = Campaign::query()->where('is_active', true)->get()->all();
        $ads = Ad::query()->where('is_active', true)->get()->all();

        /** @var array<int, int> $campaignSessions */
        $campaignSessions = [];
        /** @var array<int, array<int, true>> $campaignVisitors */
        $campaignVisitors = [];
        /** @var array<int, int> $adSessions */
        $adSessions = [];
        /** @var array<int, array<int, true>> $adVisitors */
        $adVisitors = [];

        foreach ($this->taggedSessions($period, $subjectType)->get(['visitor_id', 'mkt_params']) as $session) {
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
        $campaigns = Campaign::query()->where('is_active', true)->get()->all();
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

        foreach ($this->taggedSessions($period, $subjectType)->get(['visitor_id', 'mkt_params', 'started_at']) as $session) {
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
        $ads = Ad::query()->where('is_active', true)->get()->all();

        $sessions = 0;
        /** @var array<int, true> $visitors */
        $visitors = [];
        /** @var array<string, int> $daily */
        $daily = [];

        foreach ($this->taggedSessions($period, $subjectType)->get(['visitor_id', 'mkt_params', 'started_at']) as $session) {
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
     * @return array{total: int, campaigns: array<int, int>, ads: array<int, int>, objectives: array<int, array<string, int>>}
     */
    public function conversions(Period $period, ?string $subjectType, FunnelRegistry $funnels): array
    {
        /** @var Collection<int, Ad> $ads */
        $ads = Ad::query()->where('is_active', true)->with('objectives')->get();
        $adList = $ads->all();
        $adById = $ads->keyBy('id');

        /** @var array<int, array<int, true>> $visitorAds */
        $visitorAds = [];
        foreach ($this->taggedSessions($period, $subjectType)->get(['visitor_id', 'mkt_params']) as $session) {
            $ad = $this->mostSpecific($adList, $session->mkt_params ?? []);
            if ($ad instanceof Ad) {
                $visitorAds[(int) $session->visitor_id][$ad->id] = true;
            }
        }

        if ($visitorAds === []) {
            return ['total' => 0, 'campaigns' => [], 'ads' => [], 'objectives' => []];
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

        /** @var array<string, array<int, true>> $eventCompleters */
        $eventCompleters = [];
        if ($eventNames !== []) {
            $rows = Event::query()
                ->whereIn('visitor_id', $visitorIds)
                ->whereIn('name', array_keys($eventNames))
                ->whereBetween('occurred_at', [$period->from, $period->to])
                ->whereHas('session', fn (Builder $session): Builder => $session->where('is_bot', false))
                ->get(['visitor_id', 'name']);

            foreach ($rows as $row) {
                $eventCompleters[(string) $row->name][(int) $row->visitor_id] = true;
            }
        }

        /** @var array<string, array<int, true>> $funnelCompleters */
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

        foreach ($visitorAds as $visitorId => $adIds) {
            foreach (array_keys($adIds) as $adId) {
                $ad = $adById->get($adId);
                if (! ($ad instanceof Ad)) {
                    continue;
                }

                $convertedForAd = false;
                foreach ($ad->objectives as $objective) {
                    $completed = $objective->type === ObjectiveType::Event
                        ? isset($eventCompleters[$objective->reference][$visitorId])
                        : isset($funnelCompleters[$objective->reference][$visitorId]);

                    if ($completed) {
                        $convertedForAd = true;
                        $objectiveConverters[$adId][$objective->reference][$visitorId] = true;
                    }
                }

                if ($convertedForAd) {
                    $adConverters[$adId][$visitorId] = true;
                    $campaignConverters[$ad->campaign_id][$visitorId] = true;
                    $totalConverters[$visitorId] = true;
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
        ];
    }

    /**
     * The subset of the given visitors who completed the funnel — reached its last
     * step in chronological order — over the period, mirroring the funnels screen.
     *
     * @param  list<int>  $visitorIds
     * @return array<int, true>
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
        /** @var array<int, true> $completers */
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
                    $completers[$visitorId] = true;
                }
            }
        }

        return $completers;
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
