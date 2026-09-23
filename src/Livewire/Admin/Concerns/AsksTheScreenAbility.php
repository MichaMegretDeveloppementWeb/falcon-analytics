<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin\Concerns;

use Falcon\Analytics\Enums\Authorization\Ability;

/**
 * A screen asks the ability that opens it on every request, wherever it is
 * mounted: its route asks it too, but only the route the package mounts.
 *
 * @internal
 */
trait AsksTheScreenAbility
{
    /** The ability that opens this screen. */
    abstract protected function screenAbility(): Ability;

    /** A Livewire hook, run before the component mounts or hydrates. */
    public function bootAsksTheScreenAbility(): void
    {
        $this->authorize($this->screenAbility());
    }
}
