@props([
    'label',
    'value' => null,
    'mono' => false,
])

<div class="flex items-start justify-between gap-3">
    <dt class="shrink-0 text-[12px] text-secondary">{{ $label }}</dt>
    <dd @class([
        'min-w-0 truncate text-right',
        'font-mono text-[12px]' => $mono,
        'text-[13px]' => ! $mono,
        'text-primary' => filled($value),
        'text-muted' => ! filled($value),
    ])>{{ filled($value) ? $value : '·' }}</dd>
</div>
