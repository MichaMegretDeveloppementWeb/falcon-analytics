<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\DTOs\Dashboard\TaggedSessions;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;

/**
 * Raw database reads for the marketing screens: ad-tagged sessions, the active
 * campaign/ad definitions and the objective-event completions. No attribution
 * or aggregation happens here; the MarketingReportBuilder matches these rows
 * to campaigns and ads and derives every figure from them.
 *
 * @internal
 */
final class MarketingReadRepository
{
    /**
     * The ceiling comes from `analytics.marketing.max_sessions`; tests inject a
     * small one to exercise the truncation without hydrating thousands of rows.
     */
    public function __construct(private readonly ?int $maxTaggedSessions = null) {}

    /**
     * Ad-tagged sessions for a period, capped at the ceiling so a paid-traffic
     * spike cannot exhaust memory. Beyond it the figures under-count, and the
     * result says so for the screens to show. The builder scans these same rows
     * for every marketing figure, so a superset of the columns each read needs
     * is fetched in one query.
     */
    public function taggedSessionRows(Period $period, ?string $subjectType): TaggedSessions
    {
        $ceiling = $this->ceiling();

        $rows = $this->taggedSessions($period, $subjectType)
            ->limit($ceiling + 1)
            ->get(['id', 'visitor_id', 'source', 'mkt_params', 'started_at']);

        if ($rows->count() <= $ceiling) {
            return new TaggedSessions($rows, $ceiling, truncated: false);
        }

        Log::channel(config('analytics.log_channel'))->warning('Marketing.tagged_sessions_truncated', [
            'limit' => $ceiling,
            'from' => $period->from->toDateTimeString(),
            'to' => $period->to->toDateTimeString(),
            'subject_type' => $subjectType,
        ]);

        return new TaggedSessions($rows->take($ceiling), $ceiling, truncated: true);
    }

    /** The host's ceiling · a value that means nothing is refused, never replaced. */
    private function ceiling(): int
    {
        $ceiling = $this->maxTaggedSessions ?? config('analytics.marketing.max_sessions');

        if (! is_int($ceiling) || $ceiling < 1) {
            throw new UnexpectedValueException('analytics.marketing.max_sessions must be a whole number of at least 1.');
        }

        return $ceiling;
    }

    /**
     * @return list<Campaign>
     */
    public function activeCampaigns(): array
    {
        // `array_values` only to carry the `list` type: the keys already run from zero.
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

    public function adWithObjectives(int $id): Ad
    {
        return Ad::query()->with('objectives')->findOrFail($id);
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
