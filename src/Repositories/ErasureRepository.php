<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * What an erasure reads to know which profiles to lock, and the rows it then
 * deletes · explicitly, so the count it answers is every row that went.
 *
 * @internal
 */
final readonly class ErasureRepository
{
    /**
     * The subject's own profiles (theirs, and the aliases folded into them, which
     * carry the subject too) and the other profiles their sessions sit on, each
     * sorted by id.
     *
     * @return array{own: list<int>, hosts: list<int>}
     */
    public function profilesOf(string $type, int $id): array
    {
        $own = array_values($this->carrying(Visitor::query(), $type, $id)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $visitorId): int => (int) $visitorId)
            ->all());

        $hosts = array_values($this->carrying(Session::query(), $type, $id)
            ->whereNotIn('visitor_id', $own)
            ->distinct()
            ->orderBy('visitor_id')
            ->pluck('visitor_id')
            ->map(fn (mixed $visitorId): int => (int) $visitorId)
            ->all());

        return ['own' => $own, 'hosts' => $hosts];
    }

    /**
     * A profile and the aliases folded into it, sorted by id.
     *
     * @return list<int>
     */
    public function profileAndAliases(int $visitorId): array
    {
        return array_values(Visitor::query()
            ->whereKey($visitorId)
            ->orWhere('merged_into_id', $visitorId)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all());
    }

    /**
     * How many sessions erasing this profile takes · its own and the anonymous
     * ones, but not those another subject left there, which
     * `VisitorMerger::rehomeOtherSubjects()` moves to that subject first. Its
     * aliases hold none · a merge moves them all to the profile.
     */
    public function sessionsGoingWith(Visitor $profile): int
    {
        return Session::query()
            ->where('visitor_id', $profile->id)
            ->where(fn (Builder $theirs): Builder => $theirs
                ->whereNull('subject_type')
                ->when($profile->subject_type !== null, fn (Builder $query): Builder => $query->orWhere(
                    fn (Builder $own): Builder => $own->where('subject_type', $profile->subject_type)->where('subject_id', $profile->subject_id),
                )))
            ->count();
    }

    /**
     * The days whose summaries hold the subject's rows · the start of every
     * session carrying the subject or sitting on their profiles, and the day of
     * every event carrying it, sitting on them, or held by those sessions.
     *
     * @param  list<int>  $own
     * @return list<CarbonImmutable>
     */
    public function daysOfSubject(string $type, int $id, array $own): array
    {
        return $this->days(
            $this->sessionDays($this->carrying(Session::query(), $type, $id)),
            $this->sessionDays(Session::query()->whereIn('visitor_id', $own)),
            $this->eventDays($this->carrying(Event::query(), $type, $id)),
            $this->eventDays(Event::query()->whereIn('visitor_id', $own)),
            $this->eventDays(Event::query()->whereIn('session_id', $this->carrying(Session::query()->select('id'), $type, $id))),
        );
    }

    /**
     * The days whose summaries hold the rows of these profiles · the start of
     * each of their sessions, and the day of each of their events.
     *
     * @param  list<int>  $profiles
     * @return list<CarbonImmutable>
     */
    public function daysOfProfiles(array $profiles): array
    {
        return $this->days(
            $this->sessionDays(Session::query()->whereIn('visitor_id', $profiles)),
            $this->eventDays(Event::query()->whereIn('visitor_id', $profiles)),
        );
    }

    /**
     * Delete every row carrying the subject, whatever profile holds it, then
     * their own profiles with all they hold. Answers the number of rows deleted.
     *
     * @param  list<int>  $own
     */
    public function eraseSubject(string $type, int $id, array $own): int
    {
        $deleted = Event::query()
            ->whereIn('session_id', $this->carrying(Session::query()->select('id'), $type, $id))
            ->delete();

        $deleted += $this->carrying(Event::query(), $type, $id)->delete();
        $deleted += $this->carrying(Session::query(), $type, $id)->delete();

        return $deleted + $this->eraseProfiles($own);
    }

    /**
     * Delete these profiles and every session and event on them. Answers the
     * number of rows deleted.
     *
     * @param  list<int>  $profiles
     */
    public function eraseProfiles(array $profiles): int
    {
        $deleted = Event::query()->whereIn('visitor_id', $profiles)->delete();
        $deleted += Session::query()->whereIn('visitor_id', $profiles)->delete();

        return $deleted + Visitor::query()->whereIn('id', $profiles)->delete();
    }

    /**
     * The distinct days of several reads, oldest first, in one statement · each
     * read keeps its own index, where a single filter joined by `OR` would not.
     *
     * @return list<CarbonImmutable>
     */
    private function days(QueryBuilder $first, QueryBuilder ...$others): array
    {
        foreach ($others as $other) {
            $first->union($other);
        }

        return array_values(array_map(
            fn (mixed $day): CarbonImmutable => CarbonImmutable::parse((string) $day)->startOfDay(),
            DB::query()->fromSub($first, 'touched')->orderBy('day')->pluck('day')->all(),
        ));
    }

    /** @param  Builder<Session>  $sessions */
    private function sessionDays(Builder $sessions): QueryBuilder
    {
        return $sessions->toBase()->selectRaw('DATE(started_at) as day');
    }

    /** @param  Builder<Event>  $events */
    private function eventDays(Builder $events): QueryBuilder
    {
        return $events->toBase()->selectRaw('DATE(occurred_at) as day');
    }

    /**
     * Rows carrying the subject.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function carrying(Builder $query, string $type, int $id): Builder
    {
        return $query->where('subject_type', $type)->where('subject_id', $id);
    }
}
