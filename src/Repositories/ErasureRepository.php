<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories;

use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Illuminate\Database\Eloquent\Builder;

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
