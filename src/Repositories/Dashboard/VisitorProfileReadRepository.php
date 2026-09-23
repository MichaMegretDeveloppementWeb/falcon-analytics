<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories\Dashboard;

use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Repositories\Concerns\ScopesSessionQueries;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Read model for the visitor profile: whole-history engagement aggregates (in
 * SQL, so the page never loads every session into memory) and a paginated list
 * of the visitor's sessions.
 *
 * @internal
 */
final readonly class VisitorProfileReadRepository
{
    use ScopesSessionQueries;

    /**
     * @return array{sessions: int, pageviews: int, seconds: int, devices: array<string, int>, sources: array<string, int>}
     */
    public function engagement(int $visitorId): array
    {
        $duration = $this->durationSecondsExpression('started_at', 'last_activity_at');

        $totals = Session::query()
            ->where('visitor_id', $visitorId)
            ->selectRaw("COUNT(*) as sessions, COALESCE(SUM(pageview_count), 0) as pageviews, COALESCE(SUM({$duration}), 0) as seconds")
            ->first();

        // `first()` is typed nullable, though an aggregate without `GROUP BY` always returns a row.
        return [
            'sessions' => (int) $totals?->getAttribute('sessions'),
            'pageviews' => (int) $totals?->getAttribute('pageviews'),
            'seconds' => (int) $totals?->getAttribute('seconds'),
            'devices' => $this->breakdown($visitorId, 'device_type', ''),
            'sources' => $this->breakdown($visitorId, 'source', 'direct'),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Session>
     */
    public function paginateSessions(int $visitorId, int $perPage = 20): LengthAwarePaginator
    {
        return Session::query()
            ->where('visitor_id', $visitorId)
            ->select(['id', 'visitor_id', 'subject_type', 'subject_id', 'started_at', 'last_activity_at', 'pageview_count', 'device_type', 'browser', 'source', 'landing_route', 'landing_url', 'country', 'city'])
            ->orderByDesc('started_at')
            // Two sessions can share a start: the key makes the paginated order total.
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Session count grouped by a column, empties/nulls folded to a default bucket.
     *
     * The column enters raw SQL, so `literal-string` bars anything that is not
     * a constant from reaching `selectRaw()`.
     *
     * @param  literal-string  $column
     * @return array<string, int>
     */
    private function breakdown(int $visitorId, string $column, string $default): array
    {
        return Session::query()
            ->where('visitor_id', $visitorId)
            ->selectRaw("{$column} as bucket, COUNT(*) as total")
            ->groupBy($column)
            ->orderByDesc('total')
            ->get()
            ->mapWithKeys(function (Session $row) use ($default): array {
                $bucket = (string) $row->getAttribute('bucket');

                return [($bucket === '' ? $default : $bucket) => (int) $row->getAttribute('total')];
            })
            ->all();
    }
}
