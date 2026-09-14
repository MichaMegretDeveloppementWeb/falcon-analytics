<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\DailyArchive;
use Falcon\Analytics\Models\DailyCount;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Support\StoredUrl;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Summarises a closed day's anonymous page views and clicks, so that erasing
 * them later costs nothing.
 *
 * **The summary has to say exactly what the raw reading says**, or the two
 * blocks it feeds would jump the day the purge crosses them. So the queries
 * here mirror `OverviewReadRepository` line for line · the same bot exclusion,
 * the same subject read off the session rather than the event, the same label
 * for a click. An essay compares the two on a day where both exist, which is
 * the only way to keep them agreeing as either one moves.
 *
 * **Days are taken in order, and a day without traffic is recorded too.**
 * Advancing from the last archived day means a gap can never open; recording an
 * empty day means the sequence never stalls on one.
 *
 * @internal
 */
final readonly class DailyCountArchiver
{
    /** Rows written per statement, so a busy day does not build one huge insert. */
    private const CHUNK = 500;

    /**
     * How long after midnight a day is still considered open.
     *
     * **A row can land after the day it belongs to has ended.** The collector
     * stamps an event with the moment it happened and sends it a few seconds
     * later; the endpoint writes it after the response has gone. So a visit at
     * 23:59:58 reaches the table at 00:00:05 — and a summary written in
     * between would never count it, while the erasing would still take it. The
     * scheduler runs at 03:00 and is never caught by this; the catch-up on a
     * screen load runs whenever an administrator opens one, midnight included.
     *
     * An hour is far beyond any delay the collector can produce, and it costs
     * nothing · the nightly run comes later anyway.
     */
    private const GRACE_MINUTES = 60;

    /**
     * Summarise the days waiting for it, oldest first.
     *
     * @param  int|null  $limit  how many days at most · null takes them all,
     *                           which is what a catch-up wants
     * @return list<string> the days summarised, as Y-m-d
     */
    public function run(?int $limit = null): array
    {
        $done = [];

        foreach ($this->pendingDays($limit) as $day) {
            $this->archive($day);
            $done[] = $day->toDateString();
        }

        return $done;
    }

    /**
     * The closed days not yet summarised, oldest first.
     *
     * **It starts after the last archived day**, not at the oldest unarchived
     * one · going forward from a known point is what forbids a gap. On a
     * database that has never been archived it starts at the oldest event
     * there is, so an installation upgrading to this keeps all of its depth.
     *
     * **And it stops at the last day that is really closed** · yesterday, once
     * the grace after midnight has passed. See `GRACE_MINUTES`.
     *
     * @return list<CarbonImmutable>
     */
    public function pendingDays(?int $limit = null): array
    {
        $yesterday = CarbonImmutable::now()->subMinutes(self::GRACE_MINUTES)->subDay()->startOfDay();
        $from = $this->firstPendingDay();

        if ($from === null || $from->greaterThan($yesterday)) {
            return [];
        }

        $days = [];

        for ($day = $from; $day->lessThanOrEqualTo($yesterday); $day = $day->addDay()) {
            $days[] = $day;

            if ($limit !== null && count($days) >= $limit) {
                break;
            }
        }

        return $days;
    }

    /**
     * Summarise one day, replacing whatever was there.
     *
     * **Replacing rather than adding** is what lets it be run twice · the
     * scheduler, an administrator opening a screen and a hand-run command can
     * all land on the same day without inflating it.
     */
    public function archive(CarbonImmutable $day): void
    {
        $start = $day->startOfDay();
        $end = $day->endOfDay();

        DB::transaction(function () use ($day, $start, $end): void {
            DailyCount::query()->where('day', $day->toDateString())->delete();

            $this->write($day, DailyCount::KIND_PAGE, $this->pageRows($start, $end));
            $this->write($day, DailyCount::KIND_CLICK, $this->clickRows($start, $end));

            DailyArchive::query()->upsert(
                [['day' => $day->toDateString(), 'archived_at' => CarbonImmutable::now()->toDateTimeString()]],
                ['day'],
                ['archived_at'],
            );
        });
    }

    /** Whether a day has been summarised · what the purge asks before erasing. */
    public function isArchived(CarbonImmutable $day): bool
    {
        return DailyArchive::query()->where('day', $day->startOfDay()->toDateString())->exists();
    }

    /**
     * Where the sequence resumes · the day after the last archived one, or the
     * oldest event ever recorded when nothing has been archived yet.
     */
    private function firstPendingDay(): ?CarbonImmutable
    {
        $last = DailyArchive::query()->max('day');

        if (is_string($last) && $last !== '') {
            return CarbonImmutable::parse($last)->addDay()->startOfDay();
        }

        $oldest = Event::query()->min('occurred_at');

        return is_string($oldest) && $oldest !== ''
            ? CarbonImmutable::parse($oldest)->startOfDay()
            : null;
    }

    /**
     * Page views of the day, by address and by subject.
     *
     * The subject is read off the SESSION, as the screen does · a visitor who
     * signs in mid-session has the whole session attributed to them, and a
     * summary that read the event instead would drift from the reading it
     * stands in for.
     *
     * @return list<array{label: string, route: string|null, subject_type: string|null, total: int}>
     */
    private function pageRows(CarbonImmutable $start, CarbonImmutable $end): array
    {
        /*
         * The address WITHOUT its query string, exactly as the screen groups
         * it · see `StoredUrl`. A summary grouped differently from the reading
         * it stands in for would make the block jump the day the erasing
         * crossed it, and the essay comparing the two would be the only thing
         * standing between that and a shipped release.
         */
        $page = StoredUrl::pathExpression(DB::connection()->getDriverName(), 'url');

        $rows = $this->scope(EventType::Pageview, $start, $end)
            ->whereNotNull('url')
            ->selectRaw("{$page} as label, s.subject_type as subject_type, COUNT(*) as total")
            ->groupByRaw("{$page}, s.subject_type")
            ->get();

        return $this->shape($rows);
    }

    /**
     * Clicks of the day, by visible label, by page and by subject.
     *
     * The label prefers the text a human saw over the technical name, and the
     * resolution happens in a subquery · grouping by that expression directly
     * is refused under ONLY_FULL_GROUP_BY.
     *
     * @return list<array{label: string, route: string|null, subject_type: string|null, total: int}>
     */
    private function clickRows(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $labelled = $this->scope(EventType::Click, $start, $end)
            ->selectRaw("COALESCE(NULLIF(target_text, ''), NULLIF(name, '')) as label, route, s.subject_type as subject_type");

        $rows = DB::query()
            ->fromSub($labelled, 'clicks')
            ->whereNotNull('label')
            ->selectRaw('label, route, subject_type, COUNT(*) as total')
            ->groupBy('label', 'route', 'subject_type')
            ->get();

        return $this->shape($rows);
    }

    /**
     * The three columns every aggregate answers with, read through an array
     * rather than through dynamic properties · a query builder hands back a
     * `stdClass` whose shape nothing declares, and reading a key is something
     * the analysis can follow.
     *
     * @param  Collection<int, stdClass>  $rows
     * @return list<array{label: string, route: string|null, subject_type: string|null, total: int}>
     */
    private function shape(Collection $rows): array
    {
        return array_map(function (stdClass $row): array {
            /** @var array<string, mixed> $columns */
            $columns = (array) $row;

            $route = $columns['route'] ?? null;
            $subject = $columns['subject_type'] ?? null;

            return [
                'label' => (string) $columns['label'],
                'route' => is_scalar($route) ? (string) $route : null,
                'subject_type' => is_scalar($subject) ? (string) $subject : null,
                'total' => (int) $columns['total'],
            ];
        }, array_values($rows->all()));
    }

    /**
     * The events of one kind on one day, joined to their session for the two
     * things the screen filters on · bots out, and the subject.
     */
    private function scope(EventType $type, CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        return DB::table(Event::TABLE)
            ->join(Session::TABLE.' as s', 's.id', '=', Event::TABLE.'.session_id')
            ->where('s.is_bot', false)
            ->where(Event::TABLE.'.type', $type->value)
            ->whereBetween(Event::TABLE.'.occurred_at', [$start, $end]);
    }

    /**
     * @param  list<array{label: string, route: string|null, subject_type: string|null, total: int}>  $rows
     */
    private function write(CarbonImmutable $day, string $kind, array $rows): void
    {
        $prepared = array_map(fn (array $row): array => [
            'day' => $day->toDateString(),
            'kind' => $kind,
            'signature' => DailyCount::signature($kind, $row['label'], $row['route'], $row['subject_type']),
            'label' => $row['label'],
            'route' => $row['route'],
            'subject_type' => $row['subject_type'],
            'total' => $row['total'],
        ], $rows);

        foreach (array_chunk($prepared, self::CHUNK) as $chunk) {
            DB::table(DailyCount::TABLE)->insert($chunk);
        }
    }
}
