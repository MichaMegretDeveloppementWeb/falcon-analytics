<?php

declare(strict_types=1);

namespace Falcon\Analytics\Actions;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Services\DailyCountArchiver;
use Falcon\Analytics\Services\DailyDetailArchiver;
use Illuminate\Support\Facades\DB;

/**
 * Summarise the closed days waiting for it, oldest first, one transaction per
 * day · a day that fails is undone whole, and the days before it stay
 * summarised, so the next run resumes where this one stopped.
 *
 * Two families of summaries, each on its own sequence · the page views and
 * clicks, and the sessions and named events. A day takes whichever of the two
 * it is waiting for.
 *
 * @internal
 */
final readonly class ArchiveClosedDaysAction
{
    public function __construct(
        private DailyCountArchiver $archiver,
        private DailyDetailArchiver $details,
    ) {}

    /**
     * @param  int|null  $limit  how many days at most · null takes them all,
     *                           which is what a catch-up wants
     * @return list<string> the days summarised, as Y-m-d
     */
    public function execute(?int $limit = null): array
    {
        return $this->summarise($this->archiver->pendingDays($limit), $this->details->pendingDays($limit), $limit);
    }

    /**
     * Summarise again every closed day from `$from` on, for a writer that has
     * put rows into days already summarised · see each archiver's
     * `closedDaysFrom()` for where it really starts.
     *
     * @return list<string> the days summarised, as Y-m-d
     */
    public function executeFrom(CarbonImmutable $from): array
    {
        return $this->summarise($this->archiver->closedDaysFrom($from), $this->details->closedDaysFrom($from));
    }

    /**
     * The days of both families, oldest first, each summarised for the
     * families waiting for it.
     *
     * @param  list<CarbonImmutable>  $pages
     * @param  list<CarbonImmutable>  $details
     * @return list<string>
     */
    private function summarise(array $pages, array $details, ?int $limit = null): array
    {
        /** @var array<string, array{pages?: CarbonImmutable, details?: CarbonImmutable}> $days */
        $days = [];

        foreach ($pages as $day) {
            $days[$day->toDateString()]['pages'] = $day;
        }

        foreach ($details as $day) {
            $days[$day->toDateString()]['details'] = $day;
        }

        ksort($days);

        if ($limit !== null) {
            $days = array_slice($days, 0, $limit, true);
        }

        foreach ($days as $families) {
            DB::transaction(function () use ($families): void {
                if (isset($families['pages'])) {
                    $this->archiver->archive($families['pages']);
                }

                if (isset($families['details'])) {
                    $this->details->archive($families['details']);
                }
            });
        }

        return array_map('strval', array_keys($days));
    }
}
