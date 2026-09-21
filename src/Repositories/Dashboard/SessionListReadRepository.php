<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories\Dashboard;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Repositories\Concerns\ScopesSessionQueries;
use Falcon\Analytics\Services\SubjectResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Read model for the sessions screen: the paginated, searchable and sortable
 * session list plus the distinct device/source filter options. Bots excluded.
 *
 * @internal
 */
final readonly class SessionListReadRepository
{
    use ScopesSessionQueries;

    /**
     * Paginated session list, newest first, optionally filtered by a free-text
     * term (name / locality / id), device type and source. Each row carries the
     * count of named events and of conversions it produced (subqueries, no N+1).
     *
     * @param  list<string>  $conversionNames
     * @return LengthAwarePaginator<int, Session>
     */
    public function paginateSessions(
        Period $period,
        ?string $subjectType,
        ?string $search,
        ?string $device,
        ?string $source,
        SubjectResolver $subjects,
        string $sort = 'started_at',
        string $direction = 'desc',
        int $perPage = 20,
        array $conversionNames = [],
    ): LengthAwarePaginator {
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        $query = $this->sessionScope($period, $subjectType)
            ->select(['id', 'visitor_id', 'subject_type', 'subject_id', 'started_at', 'last_activity_at', 'pageview_count', 'source', 'landing_route', 'landing_url', 'device_type', 'browser', 'country', 'city'])
            ->with('visitor:id,uuid,subject_type,subject_id')
            ->withCount([
                'events as events_count' => fn (Builder $q): Builder => $q->whereNotNull('name'),
                'events as conversions_count' => fn (Builder $q): Builder => $q->whereIn('name', $conversionNames),
            ])
            ->when($device !== null && $device !== '', fn (Builder $q): Builder => $q->where('device_type', $device))
            ->when($source !== null && $source !== '', fn (Builder $q): Builder => $q->where('source', $source))
            ->when($search !== null && $search !== '', function (Builder $query) use ($period, $subjectType, $search, $subjects): void {
                // The `when()` test does not narrow the type inside the
                // closure, so it is done once here rather than at each of the
                // four uses below.
                $needle = (string) $search;
                $term = '%'.$needle.'%';
                $countryCodes = $this->matchingCountryCodes($period, $subjectType, $needle);

                $query->where(function (Builder $inner) use ($term, $needle, $subjects, $countryCodes): void {
                    $inner->where('city', 'like', $term)
                        ->orWhere('country', 'like', $term)
                        ->orWhereHas('visitor', fn (Builder $visitor): Builder => $visitor->where('uuid', 'like', $term));

                    if ($countryCodes !== []) {
                        $inner->orWhereIn('country', $countryCodes);
                    }

                    // Anonymous sessions display the subject stitched on their
                    // visitor (retroactive naming), so a subject match must also
                    // surface them: hence each subject clause below pairs a match
                    // on the session's own subject with a match on the visitor's
                    // stitched subject restricted to anonymous sessions.
                    if (ctype_digit($needle)) {
                        $inner->orWhere('subject_id', (int) $needle)
                            ->orWhere(fn (Builder $q): Builder => $q->whereNull('subject_id')
                                ->whereHas('visitor', fn (Builder $visitor): Builder => $visitor->where('subject_id', (int) $needle)));
                    }

                    foreach ($subjects->guards() as $guard) {
                        $ids = $subjects->matchIds($guard, $needle);

                        if ($ids !== []) {
                            $inner->orWhere(fn (Builder $q): Builder => $q->where('subject_type', $guard)->whereIn('subject_id', $ids))
                                ->orWhere(fn (Builder $q): Builder => $q->whereNull('subject_id')
                                    ->whereHas('visitor', fn (Builder $visitor): Builder => $visitor->where('subject_type', $guard)->whereIn('subject_id', $ids)));
                        }
                    }
                });
            });

        if ($sort === 'duration') {
            $query->orderByRaw($this->durationSecondsExpression('started_at', 'last_activity_at').' '.$direction);
        } else {
            // events_count / conversions_count are withCount aliases already in the
            // SELECT, so they sort without an extra query.
            $sortable = ['started_at', 'pageview_count', 'source', 'country', 'device_type', 'landing_route', 'events_count', 'conversions_count'];
            $query->orderBy(in_array($sort, $sortable, true) ? $sort : 'started_at', $direction);
        }

        // None of the sortable columns is unique, so the order above leaves ties
        // to the engine: it may then answer differently for each page, showing a
        // row twice and hiding another. The key closes the order; following the
        // requested direction keeps it on the same index as the sort.
        $query->orderBy('id', $direction);

        return $query->paginate($perPage);
    }

    /**
     * Distinct device types and sources present in the period, for the session
     * filters.
     *
     * @return array{devices: list<string>, sources: list<string>}
     */
    public function sessionFilterOptions(Period $period, ?string $subjectType): array
    {
        // Cast and reindexed here: `pluck()` returns column values that nothing
        // guarantees to be strings, and the two declared lists promise it.
        $base = fn (string $column): array => array_values($this->sessionScope($period, $subjectType)
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->map(fn (mixed $value): string => (string) $value)
            ->all());

        return [
            'devices' => $base('device_type'),
            'sources' => $base('source'),
        ];
    }

    /**
     * ISO country codes stored in sessions whose localised name (or the code
     * itself) matches the term, so the list can be searched by country name.
     * Bounded to the displayed period (the outer query is period-scoped anyway),
     * so a keystroke never scans the whole table.
     *
     * @return list<string>
     */
    private function matchingCountryCodes(Period $period, ?string $subjectType, string $search): array
    {
        if (! class_exists(\Locale::class)) {
            return [];
        }

        try {
            $codes = $this->sessionScope($period, $subjectType)->whereNotNull('country')->distinct()->pluck('country');
        } catch (\Throwable $e) {
            Log::channel(config('analytics.log_channel'))->warning('Analytics country-code lookup failed.', ['exception' => $e]);

            return [];
        }

        $locale = app()->getLocale();
        $matches = [];

        foreach ($codes as $code) {
            $code = (string) $code;
            $name = \Locale::getDisplayRegion('-'.$code, $locale);

            if (is_string($name) && $name !== '' && mb_stripos($name, $search) !== false) {
                $matches[] = $code;
            }
        }

        return $matches;
    }
}
