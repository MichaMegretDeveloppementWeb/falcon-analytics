<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Repositories\Concerns\ScopesSessionQueries;
use Illuminate\Database\Eloquent\Builder;

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
