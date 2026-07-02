<button
    type="button"
    wire:click="sortBy('{{ $column }}')"
    @class(['group inline-flex cursor-pointer items-center gap-1 whitespace-nowrap transition-colors hover:text-primary', 'flex-row-reverse' => ($align ?? 'left') === 'right'])
>
    {{ $label }}
    @if ($sort === $column)
        <x-ui.icon :name="$direction === 'asc' ? 'bars-arrow-up' : 'bars-arrow-down'" class="h-3 w-3 text-primary" />
    @else
        <x-ui.icon name="chevron-up-down" class="h-3 w-3 text-muted opacity-40 transition-opacity group-hover:opacity-100" />
    @endif
</button>
