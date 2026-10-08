<?php

declare(strict_types=1);

namespace Falcon\Analytics\Actions;

use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\ErasureRepository;
use Falcon\Analytics\Services\VisitorLocks;
use Falcon\Analytics\Services\VisitorMerger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Erase a visitor and everything attached to them (sessions, events, merged
 * aliases), for a GDPR right-to-erasure request. The sessions another subject
 * left on the profile move to that subject's own profile first. Deletes
 * explicitly, so it behaves the same whatever the foreign-key cascades.
 *
 * @internal
 */
final readonly class ForgetVisitorAction
{
    /** How many times the profiles are read and locked anew when a send moves them meanwhile. */
    private const ROUNDS = 3;

    public function __construct(
        private ErasureRepository $erasure,
        private VisitorLocks $locks,
        private VisitorMerger $merges,
    ) {}

    public function execute(Visitor $visitor): void
    {
        for ($round = 1; $round <= self::ROUNDS; $round++) {
            // What to lock is read before the transaction, so the lock is its first read.
            $profiles = $this->erasure->profileAndAliases($visitor->id);

            if (DB::transaction(fn (): bool => $this->eraseUnderTheLock($visitor->id, $profiles), attempts: 3)) {
                return;
            }
        }

        throw new RuntimeException("The profiles of visitor #{$visitor->id} kept changing while they were being erased: nothing was erased.");
    }

    /**
     * False when the profiles changed between their reading and the lock.
     *
     * @param  list<int>  $profiles
     */
    private function eraseUnderTheLock(int $visitorId, array $profiles): bool
    {
        $locked = $this->locks->lock($profiles);

        if ($this->erasure->profileAndAliases($visitorId) !== $profiles) {
            return false;
        }

        $this->merges->rehomeOtherSubjects($profiles, $this->subjectOf($locked[$visitorId] ?? null));
        $this->erasure->eraseProfiles($profiles);

        return true;
    }

    /**
     * @return array{type: string, id: int}|null
     */
    private function subjectOf(?Visitor $visitor): ?array
    {
        if ($visitor?->subject_type === null || $visitor->subject_id === null) {
            return null;
        }

        return ['type' => $visitor->subject_type, 'id' => $visitor->subject_id];
    }
}
