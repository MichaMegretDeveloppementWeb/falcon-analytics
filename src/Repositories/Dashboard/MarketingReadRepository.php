<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Raw database reads for the marketing screens: ad-tagged sessions, the active
 * campaign/ad definitions and the objective-event completions. No attribution
 * or aggregation happens here; the MarketingReportBuilder matches these rows
 * to campaigns and ads and derives every figure from them.
 */
final class MarketingReadRepository
{
    /**
     * Hard ceiling on the tagged sessions loaded for a period, so a paid-traffic
     * spike cannot exhaust memory: beyond it the report degrades (figures under-
     * count) instead of the page failing, and a warning is logged.
     */
    public const MAX_TAGGED_SESSIONS = 20000;

    /**
     * The ceiling defaults to the production constant; tests inject a small
     * one to exercise the truncation without hydrating 20k models.
     */
    public function __construct(private readonly int $maxTaggedSessions = self::MAX_TAGGED_SESSIONS) {}

    /**
     * Ad-tagged sessions for a period, capped at MAX_TAGGED_SESSIONS rows. The
     * builder scans these same rows for every marketing figure, so a superset
     * of the columns each read needs is fetched in one query.
     *
     * @return Collection<int, Session>
     */
    public function taggedSessionRows(Period $period, ?string $subjectType): Collection
    {
        $rows = $this->taggedSessions($period, $subjectType)
            ->limit($this->maxTaggedSessions + 1)
            ->get(['id', 'visitor_id', 'source', 'mkt_params', 'started_at']);

        if ($rows->count() <= $this->maxTaggedSessions) {
            return $rows;
        }

        Log::channel(config('analytics.log_channel'))->warning('Marketing.tagged_sessions_truncated', [
            'limit' => $this->maxTaggedSessions,
            'from' => $period->from->toDateTimeString(),
            'to' => $period->to->toDateTimeString(),
            'subject_type' => $subjectType,
        ]);

        return $rows->take($this->maxTaggedSessions);
    }

    /**
     * @return list<Campaign>
     */
    public function activeCampaigns(): array
    {
        // `array_values` plutot que `->all()` seul · une collection Eloquent est
        // deja indexee depuis zero, mais son type ne le dit pas, et les
        // appelants attendent une liste. Meme raison partout dans ce fichier.
        return array_values(Campaign::query()->where('is_active', true)->get()->all());
    }

    /**
     * @return Collection<int, Ad>
     */
    public function activeAdsWithObjectives(): Collection
    {
        return Ad::query()->where('is_active', true)->with('objectives')->get();
    }

    /**
     * @return list<Ad>
     */
    public function activeAdsWithCampaign(): array
    {
        return array_values(Ad::query()->where('is_active', true)->with('campaign')->get()->all());
    }

    /**
     * @return list<Ad>
     */
    public function activeCampaignAds(Campaign $campaign): array
    {
        return array_values($campaign->ads()->where('is_active', true)->get()->all());
    }

    /**
     * Every completion of the given named events by the given visitors over the
     * period, bot sessions excluded. One row per event occurrence.
     *
     * @param  list<int>  $visitorIds
     * @param  list<string>  $names
     * @return Collection<int, Event>
     */
    public function objectiveEventRows(array $visitorIds, array $names, Period $period): Collection
    {
        return $this->objectiveEvents($visitorIds, $names, $period)
            ->get(['visitor_id', 'name', 'occurred_at']);
    }

    /**
     * The distinct (visitor, event name) completion pairs for the given named
     * events over the period, bot sessions excluded.
     *
     * @param  list<int>  $visitorIds
     * @param  list<string>  $names
     * @return Collection<int, Event>
     */
    public function distinctObjectiveEventRows(array $visitorIds, array $names, Period $period): Collection
    {
        return $this->objectiveEvents($visitorIds, $names, $period)
            ->distinct()
            ->get(['visitor_id', 'name']);
    }

    /**
     * @param  list<int>  $visitorIds
     * @param  list<string>  $names
     * @return Builder<Event>
     */
    private function objectiveEvents(array $visitorIds, array $names, Period $period): Builder
    {
        return Event::query()
            ->whereIn('visitor_id', $visitorIds)
            ->whereIn('name', $names)
            ->whereBetween('occurred_at', [$period->from, $period->to])
            ->whereHas('session', fn (Builder $session): Builder => $session->where('is_bot', false));
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
}
