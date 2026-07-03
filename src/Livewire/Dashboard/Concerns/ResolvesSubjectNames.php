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
     * @param  LengthAwarePaginator<int, Model>  $records
     * @return array<string, string>
     */
    protected function resolveSubjectNames(LengthAwarePaginator $records, SubjectResolver $subjects): array
    {
        $byGuard = [];
        foreach ($records as $record) {
            if ($record->subject_type !== null && $record->subject_id !== null) {
                $byGuard[$record->subject_type][] = (int) $record->subject_id;
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
