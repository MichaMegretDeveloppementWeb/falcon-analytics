<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Models\DailyArchive;
use Falcon\Analytics\Models\DailyEventTotal;
use Falcon\Analytics\Models\DailySessionTotal;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Support\ClosedDays;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;
use stdClass;

/**
 * Summarises a closed day's sessions and named events into daily totals, so
 * that erasing them later costs no figure that adds up.
 *
 * The totals say what the raw readings say · bots out, a session under its own
 * subject, a named event under its visitor's as the events screen reads it,
 * and an empty name naming nothing. A test compares the two on a day where
 * both exist.
 *
 * Its sequence is its own, beside the page views' · it starts at the oldest
 * session or event there is, so the whole history is summarised on the first
 * pass, and it moves forward without a gap, a day without traffic recorded too.
 *
 * @internal
 */
final readonly class DailyDetailArchiver
{
    /** Rows written per statement, so a busy day does not build one huge insert. */
    private const CHUNK = 500;

    /**
     * The closed days not yet summarised, oldest first · from the day after
     * the last one summarised, or from the oldest row there is.
     *
     * @return list<CarbonImmutable>
     */
    public function pendingDays(?int $limit = null): array
    {
        $from = $this->firstPendingDay();

        return $from === null ? [] : ClosedDays::since($from, $limit);
    }

    /**
     * Every closed day from `$from` on, oldest first, for a writer that puts
     * rows into days already summarised · from the first pending day when that
     * one is earlier, so the register keeps no gap.
     *
     * @return list<CarbonImmutable>
     */
    public function closedDaysFrom(CarbonImmutable $from): array
    {
        $from = $from->startOfDay();
        $pending = $this->firstPendingDay();

        if ($pending !== null && $pending->lessThan($from)) {
            $from = $pending;
        }

        return ClosedDays::since($from);
    }

    /**
     * Summarise one day, replacing whatever was there, so it can run twice.
     *
     * The replacement stands or falls whole, so it runs inside the caller's
     * transaction and refuses to run without one.
     */
    public function archive(CarbonImmutable $day): void
    {
        $this->assertInsideATransaction(__FUNCTION__);

        $this->replaceSessions($day);
        $this->replaceEvents($day);

        DailyArchive::query()->upsert(
            [['day' => $day->toDateString(), 'detail_archived_at' => CarbonImmutable::now()->toDateTimeString()]],
            ['day'],
            ['detail_archived_at'],
        );
    }

    /**
     * Summarise again the sessions of a day already summarised, for a writer
     * that took some of them away while the others are still whole · the
     * register is left as it is.
     */
    public function summariseSessionsAgain(CarbonImmutable $day): void
    {
        $this->assertInsideATransaction(__FUNCTION__);
        $this->replaceSessions($day);
    }

    /** The same, for the named events. */
    public function summariseEventsAgain(CarbonImmutable $day): void
    {
        $this->assertInsideATransaction(__FUNCTION__);
        $this->replaceEvents($day);
    }

    /**
     * Mark every summarised day before the cutoff as having its sessions
     * erased · written before the erasing, so a screen read in between takes
     * those days from their totals.
     */
    public function markSessionsErasedBefore(CarbonImmutable $cutoff): void
    {
        $this->markErasedBefore('sessions_pruned_at', $cutoff);
    }

    /** The same, for the named events. */
    public function markEventsErasedBefore(CarbonImmutable $cutoff): void
    {
        $this->markErasedBefore('events_pruned_at', $cutoff);
    }

    private function markErasedBefore(string $column, CarbonImmutable $cutoff): void
    {
        DailyArchive::query()
            ->where('day', '<', $cutoff->toDateString())
            ->whereNotNull('detail_archived_at')
            ->whereNull($column)
            ->update([$column => CarbonImmutable::now()->toDateTimeString()]);
    }

    /** Whether a day's sessions and named events have been summarised. */
    public function isArchived(CarbonImmutable $day): bool
    {
        return DailyArchive::query()
            ->where('day', $day->startOfDay()->toDateString())
            ->whereNotNull('detail_archived_at')
            ->exists();
    }

    private function firstPendingDay(): ?CarbonImmutable
    {
        $last = DailyArchive::lastDetailSummarisedDay();

        if ($last !== null) {
            return $last->addDay();
        }

        $oldest = array_filter(
            [Session::query()->min('started_at'), Event::query()->min('occurred_at')],
            fn (mixed $moment): bool => is_string($moment) && $moment !== '',
        );

        return $oldest === [] ? null : CarbonImmutable::parse(min($oldest))->startOfDay();
    }

    private function assertInsideATransaction(string $method): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException(self::class."::{$method}() replaces a whole day: call it inside the caller's transaction.");
        }
    }

    private function replaceSessions(CarbonImmutable $day): void
    {
        $date = $day->toDateString();

        DB::table(DailySessionTotal::TABLE)->where('day', $date)->delete();
        $this->write(DailySessionTotal::TABLE, $this->sessionRows($date, $day->startOfDay(), $day->endOfDay()));
    }

    private function replaceEvents(CarbonImmutable $day): void
    {
        $date = $day->toDateString();

        DB::table(DailyEventTotal::TABLE)->where('day', $date)->delete();
        $this->write(DailyEventTotal::TABLE, $this->eventRows($date, $day->startOfDay(), $day->endOfDay()));
    }

    /**
     * The day's sessions as a whole and in each breakdown, in one read · by the
     * session's subject, as the screens read it.
     *
     * @return list<array<string, mixed>>
     */
    private function sessionRows(string $date, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = $this->sessionsBy($start, $end, DailySessionTotal::DIMENSION_ALL, 'NULL as key_a, NULL as key_b, NULL as mkt_params', [])
            ->unionAll($this->sessionsBy($start, $end, DailySessionTotal::DIMENSION_DEVICE, 'device_type as key_a, NULL as key_b, NULL as mkt_params', ['device_type']))
            ->unionAll($this->sessionsBy($start, $end, DailySessionTotal::DIMENSION_LOCALITY, 'country as key_a, city as key_b, NULL as mkt_params', ['country', 'city']))
            ->unionAll($this->sessionsBy($start, $end, DailySessionTotal::DIMENSION_SOURCE, 'source as key_a, NULL as key_b, CAST(mkt_params AS CHAR) as mkt_params', ['source', DB::raw('CAST(mkt_params AS CHAR)')]))
            ->get();

        return array_map(function (array $columns) use ($date): array {
            $dimension = (string) $columns['dimension'];
            $subject = $this->text($columns['subject_type']);
            $keyA = $this->text($columns['key_a']);
            $keyB = $this->text($columns['key_b']);
            $parameters = $this->text($columns['mkt_params']);

            return [
                'day' => $date,
                'dimension' => $dimension,
                'signature' => DailySessionTotal::signature($dimension, $subject, $keyA, $keyB, $parameters),
                'subject_type' => $subject,
                'key_a' => $keyA,
                'key_b' => $keyB,
                'mkt_params' => $parameters,
                'sessions' => (int) $columns['sessions'],
                'pageviews' => (int) $columns['pageviews'],
                'seconds' => (int) $columns['seconds'],
                'bounces' => (int) $columns['bounces'],
            ];
        }, $this->columnsOf($rows));
    }

    /**
     * One breakdown of the day's sessions · duration and bounce read as the
     * engagement figures read them.
     *
     * @param  literal-string  $dimension
     * @param  literal-string  $keys
     * @param  list<string|Expression>  $groups
     */
    private function sessionsBy(CarbonImmutable $start, CarbonImmutable $end, string $dimension, string $keys, array $groups): Builder
    {
        return DB::table(Session::TABLE)
            ->where('is_bot', false)
            ->whereBetween('started_at', [$start, $end])
            ->selectRaw(
                "'{$dimension}' as dimension, subject_type, {$keys}, "
                .'COUNT(*) as sessions, '
                .'COALESCE(SUM(pageview_count), 0) as pageviews, '
                .'COALESCE(SUM(TIMESTAMPDIFF(SECOND, started_at, last_activity_at)), 0) as seconds, '
                .'COALESCE(SUM(CASE WHEN pageview_count <= 1 THEN 1 ELSE 0 END), 0) as bounces'
            )
            ->groupBy(['subject_type', ...$groups]);
    }

    /**
     * The day's named events, by name and by the subject of the visitor who
     * sent them, as the events screen reads them.
     *
     * @return list<array<string, mixed>>
     */
    private function eventRows(string $date, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = DB::table(Event::TABLE.' as e')
            ->join(Session::TABLE.' as s', 's.id', '=', 'e.session_id')
            ->join('falcon_analytics_visitors as v', 'v.id', '=', 'e.visitor_id')
            ->where('s.is_bot', false)
            ->where('e.name', '<>', '')
            ->whereBetween('e.occurred_at', [$start, $end])
            ->selectRaw('v.subject_type as subject_type, e.name as name, COUNT(*) as total, COALESCE(SUM(e.value), 0) as value_sum, COUNT(e.value) as value_count')
            ->groupBy('v.subject_type', 'e.name')
            ->get();

        return array_map(function (array $columns) use ($date): array {
            $subject = $this->text($columns['subject_type']);
            $name = (string) $columns['name'];

            return [
                'day' => $date,
                'signature' => DailyEventTotal::signature($subject, $name),
                'subject_type' => $subject,
                'name' => $name,
                'total' => (int) $columns['total'],
                'value_sum' => (int) $columns['value_sum'],
                'value_count' => (int) $columns['value_count'],
            ];
        }, $this->columnsOf($rows));
    }

    /**
     * Each row as an array · a query builder hands back a `stdClass` whose
     * shape nothing declares, and static analysis can follow a key.
     *
     * @param  Collection<int, stdClass>  $rows
     * @return list<array<string, mixed>>
     */
    private function columnsOf(Collection $rows): array
    {
        return array_map(fn (stdClass $row): array => (array) $row, array_values($rows->all()));
    }

    private function text(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function write(string $table, array $rows): void
    {
        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
