@props([
    'label' => '',
    'value' => '',
    'icon' => null,
    'metric' => null,   // MetricDelta (has ->current, ->previous, ->hasBaseline(), ->changePercent())
    'inverse' => false, // true when a lower value is better (e.g. bounce rate)
    'description' => null,
    'retention' => null, // days the figure's rows are kept, said when the figure is unavailable · the sessions' by default
])

@php
    use Falcon\Analytics\Support\NumberLabel;
    use Falcon\Analytics\Support\RetentionLabel;
    use Falcon\Analytics\Support\RetentionSettings;

    // Delta badge computed here so the arrow always follows the value's direction
    // while the colour follows whether that direction is good, the two are
    // independent (the ui-kit stat-card couples them, which misreads inverse
    // metrics). An "up from an empty previous period" reads as +100 %.
    $badge = null;
    $unavailable = $metric !== null && ! $metric->isAvailable();
    $noComparison = $metric !== null && $metric->isAvailable() && ! $metric->isComparable();

    if ($metric !== null && $metric->isComparable()) {
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
                'text' => ($pct > 0 ? '+' : '−').NumberLabel::percent(abs($pct)),
            ];
        }
    }
@endphp

<div {{ $attributes->merge(['class' => 'an:rounded-xl an:bg-surface an:px-4 an:py-3.5 an:sm:px-5 an:sm:py-4']) }}>
    <div class="an:flex an:items-center an:justify-between">
        <p class="an:text-[12px] an:font-medium an:text-secondary an:sm:text-[13px]">{{ $label }}</p>
        @if ($icon)
            <span class="an:flex an:h-8 an:w-8 an:items-center an:justify-center an:rounded-lg an:bg-elevated">
                <x-ui::icon :name="$icon" class="an:h-4 an:w-4 an:text-muted" />
            </span>
        @endif
    </div>
    @if ($unavailable)
        <p class="an:mt-1 an:text-[13px] an:text-muted an:sm:mt-1.5">{{ RetentionLabel::unavailable($retention ?? RetentionSettings::sessions()) }}</p>
    @else
    <div class="an:mt-1 an:flex an:flex-wrap an:items-baseline an:gap-x-2 an:sm:mt-1.5">
        <span class="an:text-xl an:font-semibold an:tracking-tight an:text-primary an:sm:text-2xl">{{ $value }}</span>
        @if ($badge)
            <span @class([
                'an:inline-flex an:items-center an:gap-x-0.5 an:rounded-full an:px-1.5 an:py-0.5 an:text-[11px] an:font-medium',
                'an:bg-emerald-50 an:text-emerald-700 an:dark:bg-emerald-500/10 an:dark:text-emerald-400' => $badge['good'],
                'an:bg-red-50 an:text-red-600 an:dark:bg-red-500/10 an:dark:text-red-400' => ! $badge['good'],
            ])>
                <x-ui::icon :name="$badge['up'] ? 'arrow-up-right' : 'arrow-down-right'" class="an:h-3 an:w-3" stroke-width="2.5" />
                {{ $badge['text'] }}
            </span>
        @endif
        @if ($noComparison)
            <span class="an:text-[11px] an:text-muted">{{ RetentionLabel::noComparison() }}</span>
        @endif
    </div>
    @endif
    @if ($description)
        <p class="an:mt-0.5 an:text-[11px] an:text-muted an:sm:mt-1">{{ $description }}</p>
    @endif
    {{ $slot }}
</div>
