<?php

declare(strict_types=1);

namespace Falcon\Analytics\Actions;

use Falcon\Analytics\Repositories\ErasureRepository;
use Falcon\Analytics\Repositories\VisitorWriteRepository;
use Falcon\Analytics\Services\ErasedDaysArchiver;
use Falcon\Analytics\Services\VisitorLocks;
use Falcon\Analytics\Services\VisitorMerger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Erase everything held on one subject of the host · their profiles and the
 * aliases folded into them, every session and event on those, and every row
 * that carries their subject on another profile. The sessions other subjects
 * left on their profiles move to those subjects' own profiles first. The days
 * still read from their rows are summarised again in the same transaction · the
 * older totals keep the person, naming no one.
 *
 * @internal
 */
final readonly class ForgetSubjectAction
{
    /** How many times the profiles are read and locked anew when a send moves them meanwhile. */
    private const ROUNDS = 3;

    public function __construct(
        private ErasureRepository $erasure,
        private VisitorLocks $locks,
        private VisitorMerger $merges,
        private VisitorWriteRepository $visitors,
        private ErasedDaysArchiver $summaries,
    ) {}

    /** Answers the number of rows erased. */
    public function execute(string $type, int $id): int
    {
        for ($round = 1; $round <= self::ROUNDS; $round++) {
            // What to lock is read before the transaction, so the lock is its first read.
            $profiles = $this->erasure->profilesOf($type, $id);

            $erased = DB::transaction(fn (): ?int => $this->eraseUnderTheLock($type, $id, $profiles), attempts: 3);

            if ($erased !== null) {
                return $erased;
            }
        }

        throw new RuntimeException("The profiles of subject {$type} #{$id} kept changing while they were being erased: nothing was erased.");
    }

    /**
     * Null when the profiles changed between their reading and the lock.
     *
     * @param  array{own: list<int>, hosts: list<int>}  $profiles
     */
    private function eraseUnderTheLock(string $type, int $id, array $profiles): ?int
    {
        $this->locks->lock([...$profiles['own'], ...$profiles['hosts']]);

        if ($this->erasure->profilesOf($type, $id) !== $profiles) {
            return null;
        }

        $days = $this->erasure->daysOfSubject($type, $id, $profiles['own']);

        $this->merges->rehomeOtherSubjects($profiles['own'], ['type' => $type, 'id' => $id]);
        $erased = $this->erasure->eraseSubject($type, $id, $profiles['own']);
        $this->visitors->recountSessions($profiles['hosts']);
        $this->summaries->summariseAgain($days);

        return $erased;
    }
}
