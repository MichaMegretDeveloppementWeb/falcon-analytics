<?php

declare(strict_types=1);

namespace Falcon\Analytics\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Raw reads against a host guard's own table (users, lessors, ...), which have no
 * Eloquent model in this agnostic package. Kept out of SubjectResolver so that
 * service stays pure config resolution and label formatting. Every read degrades to
 * empty on failure, since a broken or absent host schema must never break a read.
 */
final class SubjectReadRepository
{
    /**
     * Upper bound on ids returned by a subject search, so a broad term can never
     * pull an unbounded set into the caller's WHERE IN.
     */
    private const MATCH_LIMIT = 200;

    /**
     * Ids from the table matching the term, capped at a safe bound. The term is
     * split on whitespace and every word must match one of the columns (LIKE),
     * so a full name spanning two columns ("René Roy") matches too.
     *
     * @param  list<string>  $columns
     * @return list<int>
     */
    public function matchingIds(string $table, string $key, array $columns, string $term): array
    {
        $words = preg_split('/\s+/', trim($term), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return [];
        }

        try {
            // `array_values` · le resultat est deja indexe depuis zero, mais son
            // type ne le dit pas et cette methode declare une liste.
            return array_values(DB::table($table)
                ->where(function ($query) use ($columns, $words): void {
                    foreach ($words as $word) {
                        $query->where(function ($inner) use ($columns, $word): void {
                            foreach ($columns as $column) {
                                $inner->orWhere($column, 'like', '%'.$word.'%');
                            }
                        });
                    }
                })
                ->limit(self::MATCH_LIMIT)
                ->pluck($key)
                ->map(fn ($value): int => (int) $value)
                ->all());
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->warning('Subject.id_search_failed', ['exception' => $e]);

            return [];
        }
    }

    /**
     * Rows from the table for the given ids, selecting only the given columns.
     *
     * @param  list<int>  $ids
     * @param  list<string>  $select
     * @return list<\stdClass>
     */
    public function rows(string $table, string $key, array $ids, array $select): array
    {
        try {
            return array_values(DB::table($table)->whereIn($key, $ids)->get($select)->all());
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->warning('Subject.name_lookup_failed', ['exception' => $e]);

            return [];
        }
    }
}
