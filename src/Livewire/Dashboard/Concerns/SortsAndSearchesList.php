<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Concerns;

/**
 * Sorting and pagination-reset behaviour shared by the paginated dashboard lists
 * (sessions, visitors). The consuming component declares its own $search, $sort
 * and $direction properties (their defaults differ per screen) and uses
 * Livewire\WithPagination for resetPage().
 *
 * @property string $search
 * @property string $sort
 * @property string $direction
 */
trait SortsAndSearchesList
{
    public function sortBy(string $column): void
    {
        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->direction = 'desc';
        }

        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPeriod(): void
    {
        $this->resetPage();
    }

    public function updatedSubject(): void
    {
        $this->resetPage();
    }
}
