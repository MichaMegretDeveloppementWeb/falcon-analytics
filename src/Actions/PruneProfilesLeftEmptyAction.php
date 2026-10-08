<?php

declare(strict_types=1);

namespace Falcon\Analytics\Actions;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Services\VisitorLocks;
use Falcon\Analytics\Support\RetentionSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Erase the visitor profiles the purge of sessions left with none, last seen
 * before the retention, with the aliases folded into them · an alias never
 * goes on its own. In batches, each in its transaction.
 *
 * Each batch takes the profiles' lock first and reads them again under it · a
 * visitor coming back meanwhile has a session again, and keeps the profile.
 *
 * @internal
 */
final readonly class PruneProfilesLeftEmptyAction
{
    public function __construct(private VisitorLocks $locks) {}

    /** Answers the number of profiles erased, aliases included. */
    public function execute(int $batchSize = 500): int
    {
        $days = RetentionSettings::refusal() === null ? RetentionSettings::sessions() : null;

        if ($days === null) {
            return 0;
        }

        $cutoff = CarbonImmutable::now()->subDays($days)->startOfDay();
        $deleted = 0;

        do {
            // What to lock is read before the transaction, so the lock is its first read.
            $profiles = $this->leftEmpty(Visitor::query(), $cutoff)->orderBy('id')->limit($batchSize)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

            if ($profiles === []) {
                break;
            }

            $aliases = Visitor::query()->whereIn('merged_into_id', $profiles)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

            $deleted += DB::transaction(fn (): int => $this->eraseUnderTheLock(array_values($profiles), array_values($aliases), $cutoff), attempts: 3);
        } while (count($profiles) === $batchSize);

        return $deleted;
    }

    /**
     * @param  list<int>  $profiles
     * @param  list<int>  $aliases
     */
    private function eraseUnderTheLock(array $profiles, array $aliases, CarbonImmutable $cutoff): int
    {
        $this->locks->lock([...$profiles, ...$aliases]);

        $still = $this->leftEmpty(Visitor::query()->whereIn('id', $profiles), $cutoff)->pluck('id')->all();

        if ($still === []) {
            return 0;
        }

        return Visitor::query()->whereIn('merged_into_id', $still)->delete()
            + Visitor::query()->whereIn('id', $still)->delete();
    }

    /**
     * Profiles of their own, with no session left, last seen before the cutoff.
     *
     * @param  Builder<Visitor>  $query
     * @return Builder<Visitor>
     */
    private function leftEmpty(Builder $query, CarbonImmutable $cutoff): Builder
    {
        return $query
            ->whereNull('merged_into_id')
            ->where('last_seen_at', '<', $cutoff)
            ->whereDoesntHave('sessions');
    }
}
