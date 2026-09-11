@props([
    'label',
    'value' => null,
    'mono' => false,
    'icon' => null,
])

<div class="an:flex an:items-start an:justify-between an:gap-3">
    <dt class="an:flex an:shrink-0 an:items-center an:gap-1.5 an:text-[12px] an:text-secondary">
        @if ($icon)
            <x-ui::icon :name="$icon" class="an:h-3.5 an:w-3.5 an:shrink-0 an:text-muted" />
        @endif
        {{ $label }}
    </dt>
    <dd @class([
        'an:min-w-0 an:truncate an:text-right',
        'an:font-mono an:text-[12px]' => $mono,
        'an:text-[13px]' => ! $mono,
        'an:text-primary' => $slot->isNotEmpty() || filled($value),
        'an:text-muted' => $slot->isEmpty() && ! filled($value),
    ])>{{ $slot->isNotEmpty() ? $slot : (filled($value) ? $value : '·') }}</dd>
</div>
