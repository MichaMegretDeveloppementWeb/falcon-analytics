<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories;

use Falcon\Analytics\DTOs\Dashboard\Period;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Repositories\Concerns\ScopesSessionQueries;
use Falcon\Analytics\Services\SubjectResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read model for the sessions screen: the paginated, searchable and sortable
 * session list plus the distinct device/source filter options. Bots excluded.
 */
final readonly class SessionsReadRepository
{
    use ScopesSessionQueries;

    /**
     * Paginated session list, newest first, optionally filtered by a free-text
     * term (name / locality / id), device type and source.
     *
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
    ): LengthAwarePaginator {
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        $query = $this->sessionScope($period, $subjectType)
            ->select(['id', 'visitor_id', 'subject_type', 'subject_id', 'started_at', 'last_activity_at', 'pageview_count', 'source', 'landing_route', 'landing_url', 'device_type', 'browser', 'country', 'city'])
            ->with('visitor:id,uuid,subject_type,subject_id')
            ->when($device !== null && $device !== '', fn (Builder $q): Builder => $q->where('device_type', $device))
            ->when($source !== null && $source !== '', fn (Builder $q): Builder => $q->where('source', $source))
            ->when($search !== null && $search !== '', function (Builder $query) use ($search, $subjects): void {
                $term = '%'.$search.'%';
                $countryCodes = $this->matchingCountryCodes($search);

                $query->where(function (Builder $inner) use ($term, $search, $subjects, $countryCodes): void {
                    $inner->where('city', 'like', $term)
                        ->orWhere('country', 'like', $term)
                        ->orWhereHas('visitor', fn (Builder $visitor): Builder => $visitor->where('uuid', 'like', $term));

                    if ($countryCodes !== []) {
                        $inner->orWhereIn('country', $countryCodes);
                    }

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

        if ($sort === 'duration') {
            $query->orderByRaw($this->durationSecondsExpression('started_at', 'last_activity_at').' '.$direction);
        } else {
            $sortable = ['started_at', 'pageview_count', 'source', 'country', 'device_type', 'landing_route'];
            $query->orderBy(in_array($sort, $sortable, true) ? $sort : 'started_at', $direction);
        }

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
        $base = fn (string $column): array => $this->sessionScope($period, $subjectType)
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->all();

        return [
            'devices' => $base('device_type'),
            'sources' => $base('source'),
        ];
    }

    /**
     * ISO country codes stored in sessions whose localised name (or the code
     * itself) matches the term, so the list can be searched by country name.
     *
     * @return list<string>
     */
    private function matchingCountryCodes(string $search): array
    {
        if (! class_exists(\Locale::class)) {
            return [];
        }

        try {
            $codes = Session::query()->whereNotNull('country')->distinct()->pluck('country');
        } catch (\Throwable) {
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
