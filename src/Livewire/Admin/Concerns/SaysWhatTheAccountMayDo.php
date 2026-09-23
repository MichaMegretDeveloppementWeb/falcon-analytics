<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin\Concerns;

use Falcon\Analytics\Enums\Authorization\Ability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * What the account may do on each row of a list, asked once per render and
 * with the row itself, so a rule that reads the row hides the right buttons.
 *
 * @internal
 */
trait SaysWhatTheAccountMayDo
{
    /**
     * @param  iterable<Model>  $rows
     * @return array<int, bool> keyed by the row's id
     */
    protected function allowedOn(Ability $ability, iterable $rows): array
    {
        $allowed = [];

        foreach ($rows as $row) {
            $allowed[(int) $row->getKey()] = Gate::allows($ability, $row);
        }

        return $allowed;
    }
}
