<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Models\DailyArchive;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Summarises again, inside an erasure's transaction, the days it took rows
 * from · each family only where that day's rows are still whole.
 *
 * A day still read from its rows gets totals that say what its rows now say,
 * so no figure moves when the line later passes it. A day already read from
 * its totals keeps them · its rows are gone, and the person counted there is
 * named nowhere.
 *
 * @internal
 */
final readonly class ErasedDaysArchiver
{
    public function __construct(
        private DailyCountArchiver $pages,
        private DailyDetailArchiver $details,
    ) {}

    /**
     * @param  list<CarbonImmutable>  $days  the days the erasure took rows from
     */
    public function summariseAgain(array $days): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException(self::class.'::summariseAgain() must stand or fall with the erasure: call it inside its transaction.');
        }

        if ($days === []) {
            return;
        }

        $pagesWholeFrom = $this->pages->firstDayKept();

        // One day at a time, as the nightly pass · each summary replaces its day whole, and there are as many as days the person came on.
        foreach ($this->registerOf($days) as $day) {
            if ($day->archived_at !== null && ($pagesWholeFrom === null || $day->day->greaterThanOrEqualTo($pagesWholeFrom))) {
                $this->pages->archive($day->day);
            }

            if ($day->detail_archived_at === null) {
                continue;
            }

            if ($day->sessions_pruned_at === null) {
                $this->details->summariseSessionsAgain($day->day);
            }

            if ($day->events_pruned_at === null) {
                $this->details->summariseEventsAgain($day->day);
            }
        }
    }

    /**
     * What the register holds on those days · a day it does not hold is not
     * summarised yet, and the nightly pass will read it without the person.
     *
     * @param  list<CarbonImmutable>  $days
     * @return list<DailyArchive>
     */
    private function registerOf(array $days): array
    {
        return array_values(DailyArchive::query()
            ->whereIn('day', array_map(fn (CarbonImmutable $day): string => $day->toDateString(), $days))
            ->orderBy('day')
            ->get(['day', 'archived_at', 'detail_archived_at', 'sessions_pruned_at', 'events_pruned_at'])
            ->all());
    }
}
