<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Repositories\Concerns\ScopesSessionQueries;
use Falcon\Analytics\Services\SubjectResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Read model for the visitors screen: raw counts and daily rows feeding the KPI
 * calculator, plus the paginated visitor list. Bots excluded.
 */
final readonly class VisitorListReadRepository
{
    use ScopesSessionQueries;

    /**
     * Raw visitor counts for the period: distinct active visitors, distinct new
     * visitors (first seen within the period) and total sessions. No derivation;
     * the calculator turns these into metrics.
     *
     * @return array{visitors: int, new: int, sessions: int}
     */
    public function visitorCounts(Period $period, ?string $subjectType): array
    {
        $totals = $this->sessionScope($period, $subjectType)
            ->toBase()
            ->selectRaw('COUNT(*) as sessions, COUNT(DISTINCT visitor_id) as visitors')
            ->first();

        $new = $this->sessionScope($period, $subjectType)
            ->whereHas('visitor', fn (Builder $visitor): Builder => $visitor->whereBetween('first_seen_at', [$period->from, $period->to]))
            ->distinct()
            ->count('visitor_id');

        return [
            'visitors' => (int) ($totals->visitors ?? 0),
            'new' => (int) $new,
            'sessions' => (int) ($totals->sessions ?? 0),
        ];
    }

    /**
     * Raw per-day rows for the period, keyed by 'Y-m-d': active visitors and
     * sessions per day, and new visitors bucketed by their first seen day (only
     * visitors with a real, non-bot session). No zero-fill or ratio; that is
     * the calculator's job.
     *
     * @return array{active: array<string, array{sessions: int, visitors: int}>, new: array<string, int>}
     */
    public function visitorDailyRows(Period $period, ?string $subjectType): array
    {
        $sessionDay = $this->dayExpression('started_at');
        $active = $this->sessionScope($period, $subjectType)
            ->toBase()
            ->selectRaw("{$sessionDay} as day, COUNT(*) as sessions, COUNT(DISTINCT visitor_id) as visitors")
            ->groupBy(DB::raw($sessionDay))
            ->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->day => [
                'sessions' => (int) $row->sessions,
                'visitors' => (int) $row->visitors,
            ]])
            ->all();

        $seenDay = $this->dayExpression('first_seen_at');
        $new = Visitor::query()
            ->when($subjectType !== null, fn (Builder $q): Builder => $q->where('subject_type', $subjectType))
            ->whereBetween('first_seen_at', [$period->from, $period->to])
            ->whereHas('sessions', fn (Builder $session): Builder => $session->where('is_bot', false)->whereBetween('started_at', [$period->from, $period->to]))
            ->toBase()
            ->selectRaw("{$seenDay} as day, COUNT(*) as total")
            ->groupBy(DB::raw($seenDay))
            ->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->day => (int) $row->total])
            ->all();

        return ['active' => $active, 'new' => $new];
    }

    /**
     * Paginated visitor directory: every real profile, with their all-time
     * session count, first/last seen, the locality of their latest session and
     * their acquisition source (the very first session's source). Deliberately
     * NOT bounded to the dashboard period: the directory reflects the general
     * state of the population, only the headline KPIs read the period. Merged
     * aliases and bot-only visitors are excluded.
     *
     * Search covers visitor-level attributes (uuid, subject id and resolved
     * name); locality is intentionally excluded, as it is a per-session
     * subquery too costly to filter on at the visitor level.
     *
     * @return LengthAwarePaginator<int, Visitor>
     */
    public function paginateVisitors(
        ?string $subjectType,
        ?string $search,
        SubjectResolver $subjects,
        string $sort = 'last_seen_at',
        string $direction = 'desc',
        int $perPage = 20,
    ): LengthAwarePaginator {
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        $latest = fn (string $column): Builder => Session::query()
            ->select($column)
            ->whereColumn('visitor_id', 'falcon_analytics_visitors.id')
            ->where('is_bot', false)
            ->orderByDesc('started_at')
            ->limit(1);

        $query = Visitor::query()
            ->select(['falcon_analytics_visitors.id', 'uuid', 'first_seen_at', 'last_seen_at', 'subject_type', 'subject_id', 'session_count'])
            ->whereNull('merged_into_id')
            ->when($subjectType !== null, fn (Builder $q): Builder => $q->where('subject_type', $subjectType))
            ->whereHas('sessions', fn (Builder $q): Builder => $q->where('is_bot', false))
            ->addSelect([
                'last_country' => $latest('country'),
                'last_city' => $latest('city'),
                'acquisition_source' => Session::query()
                    ->select('source')
                    ->whereColumn('visitor_id', 'falcon_analytics_visitors.id')
                    ->where('is_bot', false)
                    ->orderBy('started_at')
                    ->limit(1),
            ])
            ->when($search !== null && $search !== '', function (Builder $query) use ($search, $subjects): void {
                $term = '%'.$search.'%';

                $query->where(function (Builder $inner) use ($term, $search, $subjects): void {
                    $inner->where('uuid', 'like', $term);

                    if (ctype_digit($search)) {
                        $inner->orWhere('subject_id', (int) $search);
                    }

                    foreach ($subjects->guards() as $guard) {
                        $ids = $subjects->matchIds($guard, $search);

                        if ($ids !== []) {
                            $inner->orWhere(fn (Builder $q): Builder => $q->where('subject_type', $guard)->whereIn('subject_id', $ids));
                        }
                    }
                });
            });

        $sortable = ['last_seen_at', 'first_seen_at', 'session_count'];
        $query->orderBy(in_array($sort, $sortable, true) ? $sort : 'last_seen_at', $direction);

        return $query->paginate($perPage);
    }
}
