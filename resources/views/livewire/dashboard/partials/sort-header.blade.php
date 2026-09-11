<button
    type="button"
    wire:click="sortBy('{{ $column }}')"
    @class(['an:group an:inline-flex an:cursor-pointer an:items-center an:gap-1 an:whitespace-nowrap an:transition-colors an:hover:text-primary', 'an:flex-row-reverse' => ($align ?? 'left') === 'right'])
>
    {{ $label }}
    @if ($sort === $column)
        <x-ui::icon :name="$direction === 'asc' ? 'bars-arrow-up' : 'bars-arrow-down'" class="an:h-3 an:w-3 an:text-primary" />
    @else
        <x-ui::icon name="chevron-up-down" class="an:h-3 an:w-3 an:text-muted an:opacity-40 an:transition-opacity an:group-hover:opacity-100" />
    @endif
</button>
