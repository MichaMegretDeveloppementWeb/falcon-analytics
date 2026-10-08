<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services;

use Falcon\Analytics\Models\Visitor;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The lock every writer of a visitor profile takes · its row, as the first read
 * of the caller's transaction, several rows in ascending id order and one
 * statement each, so two writers never wait on each other in a circle.
 *
 * @internal
 */
final readonly class VisitorLocks
{
    /** Lock one profile, and answer it as it stands now · null when it is gone. */
    public function one(int $id): ?Visitor
    {
        return $this->lock([$id])[$id] ?? null;
    }

    /**
     * Lock these profiles, and answer those still there, by id.
     *
     * @param  list<int>  $ids
     * @return array<int, Visitor>
     */
    public function lock(array $ids): array
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A visitor lock taken outside a transaction is released at once: take it inside the caller\'s transaction.');
        }

        $ids = array_unique($ids);
        sort($ids);

        $locked = [];

        foreach ($ids as $id) {
            $visitor = Visitor::query()->whereKey($id)->lockForUpdate()->first();

            if ($visitor !== null) {
                $locked[$id] = $visitor;
            }
        }

        return $locked;
    }
}
