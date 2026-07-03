<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Concerns;

use Falcon\Analytics\Services\SubjectResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

/**
 * Batch-resolves a "guard:id" => name map for the records on the current page,
 * one query per guard, so a list never triggers per-row lookups. Shared by the
 * session and visitor lists.
 */
trait ResolvesSubjectNames
{
    /**
     * @template TModel of Model
     *
     * @param  LengthAwarePaginator<int, TModel>  $records
     * @return array<string, string>
     */
    protected function resolveSubjectNames(LengthAwarePaginator $records, SubjectResolver $subjects): array
    {
        $byGuard = [];
        foreach ($records->items() as $record) {
            $guard = $record->getAttribute('subject_type');
            $id = $record->getAttribute('subject_id');

            if ($guard !== null && $id !== null) {
                $byGuard[(string) $guard][] = (int) $id;
            }
        }

        $names = [];
        foreach ($byGuard as $guard => $ids) {
            foreach ($subjects->names($guard, $ids) as $id => $name) {
                $names[$guard.':'.$id] = $name;
            }
        }

        return $names;
    }
}
