<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Concerns;

use Falcon\Analytics\DTOs\Dashboard\SessionSubjectAttribution;
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

        return $this->namesByGuard($byGuard, $subjects);
    }

    /**
     * Same map, but from session attributions (own subject or the visitor's
     * stitched subject), so retroactively named sessions resolve too.
     *
     * @param  array<int, SessionSubjectAttribution>  $attributions
     * @return array<string, string>
     */
    protected function resolveAttributedNames(array $attributions, SubjectResolver $subjects): array
    {
        $byGuard = [];
        foreach ($attributions as $attribution) {
            $byGuard[$attribution->guard][] = $attribution->id;
        }

        return $this->namesByGuard($byGuard, $subjects);
    }

    /**
     * @param  array<string, list<int>>  $byGuard
     * @return array<string, string>
     */
    private function namesByGuard(array $byGuard, SubjectResolver $subjects): array
    {
        $names = [];
        foreach ($byGuard as $guard => $ids) {
            foreach ($subjects->names($guard, $ids) as $id => $name) {
                $names[$guard.':'.$id] = $name;
            }
        }

        return $names;
    }
}
