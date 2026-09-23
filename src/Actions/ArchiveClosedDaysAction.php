<?php

declare(strict_types=1);

namespace Falcon\Analytics\Actions;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Services\DailyCountArchiver;
use Illuminate\Support\Facades\DB;

/**
 * Summarise the closed days waiting for it, oldest first, one transaction per
 * day · a day that fails is undone whole, and the days before it stay
 * summarised, so the next run resumes where this one stopped.
 *
 * @internal
 */
final readonly class ArchiveClosedDaysAction
{
    public function __construct(private DailyCountArchiver $archiver) {}

    /**
     * @param  int|null  $limit  how many days at most · null takes them all,
     *                           which is what a catch-up wants
     * @return list<string> the days summarised, as Y-m-d
     */
    public function execute(?int $limit = null): array
    {
        return $this->summarise($this->archiver->pendingDays($limit));
    }

    /**
     * Summarise again every closed day from `$from` on, for a writer that has
     * put rows into days already summarised · see
     * `DailyCountArchiver::closedDaysFrom()` for where it really starts.
     *
     * @return list<string> the days summarised, as Y-m-d
     */
    public function executeFrom(CarbonImmutable $from): array
    {
        return $this->summarise($this->archiver->closedDaysFrom($from));
    }

    /**
     * @param  list<CarbonImmutable>  $days
     * @return list<string>
     */
    private function summarise(array $days): array
    {
        $done = [];

        foreach ($days as $day) {
            DB::transaction(fn () => $this->archiver->archive($day));
            $done[] = $day->toDateString();
        }

        return $done;
    }
}
