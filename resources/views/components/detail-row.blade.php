@props([
    'label',
    'value' => null,
    'mono' => false,
    'icon' => null,
])

<div class="flex items-start justify-between gap-3">
    <dt class="flex shrink-0 items-center gap-1.5 text-[12px] text-secondary">
        @if ($icon)
            <x-ui.icon :name="$icon" class="h-3.5 w-3.5 shrink-0 text-muted" />
        @endif
        {{ $label }}
    </dt>
    <dd @class([
        'min-w-0 truncate text-right',
        'font-mono text-[12px]' => $mono,
        'text-[13px]' => ! $mono,
        'text-primary' => $slot->isNotEmpty() || filled($value),
        'text-muted' => $slot->isEmpty() && ! filled($value),
    ])>{{ $slot->isNotEmpty() ? $slot : (filled($value) ? $value : '·') }}</dd>
</div>
