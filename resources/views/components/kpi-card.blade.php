@props([
    'label' => '',
    'value' => '',
    'icon' => null,
    'metric' => null,   // MetricDelta (has ->current, ->previous, ->hasBaseline(), ->changePercent())
    'inverse' => false, // true when a lower value is better (e.g. bounce rate)
    'description' => null,
])

@php
    // Delta badge computed here so the arrow always follows the value's direction
    // while the colour follows whether that direction is good, the two are
    // independent (the ui-kit stat-card couples them, which misreads inverse
    // metrics). An "up from an empty previous period" reads as +100 %.
    $badge = null;

    if ($metric !== null) {
        $up = $metric->current > $metric->previous;

        if ($metric->hasBaseline()) {
            $pct = (int) round($metric->changePercent());
        } else {
            $pct = $metric->current > 0 ? 100 : 0;
            $up = $pct > 0;
        }

        if ($pct !== 0) {
            $badge = [
                'up' => $up,
                'good' => $inverse ? ! $up : $up,
                'text' => ($pct > 0 ? '+' : '−').number_format(abs($pct), 0, ',', ' ')."\u{00A0}%",
            ];
        }
    }
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl bg-surface px-4 py-3.5 sm:px-5 sm:py-4']) }}>
    <div class="flex items-center justify-between">
        <p class="text-[12px] font-medium text-secondary sm:text-[13px]">{{ $label }}</p>
        @if ($icon)
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-elevated">
                <x-ui.icon :name="$icon" class="h-4 w-4 text-muted" />
            </span>
        @endif
    </div>
    <div class="mt-1 flex items-baseline gap-x-2 sm:mt-1.5">
        <span class="text-xl font-semibold tracking-tight text-primary sm:text-2xl">{{ $value }}</span>
        @if ($badge)
            <span @class([
                'inline-flex items-center gap-x-0.5 rounded-full px-1.5 py-0.5 text-[11px] font-medium',
                'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' => $badge['good'],
                'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400' => ! $badge['good'],
            ])>
                <x-ui.icon :name="$badge['up'] ? 'arrow-up-right' : 'arrow-down-right'" class="h-3 w-3" stroke-width="2.5" />
                {{ $badge['text'] }}
            </span>
        @endif
    </div>
    @if ($description)
        <p class="mt-0.5 text-[11px] text-muted sm:mt-1">{{ $description }}</p>
    @endif
    {{ $slot }}
</div>
