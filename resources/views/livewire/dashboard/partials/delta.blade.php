@php
    // Params: $current, $previous, optional $inverse (true when lower is better).
    // Magnitude only: the arrow shows the direction and the colour shows whether
    // it is good. Hidden without a baseline or when the change rounds to 0 %.
    $deltaInverse = $inverse ?? false;
    $deltaHasBaseline = ((float) $previous) != 0.0;
    $deltaUp = $current > $previous;
    $deltaGood = $deltaInverse ? ! $deltaUp : $deltaUp;
    $deltaPct = $deltaHasBaseline ? (int) round((($current - $previous) / $previous) * 100) : 0;
    $deltaShow = $deltaHasBaseline && $deltaPct !== 0;
@endphp

@if ($deltaShow)
    <span @class([
        'inline-flex items-center gap-x-0.5 rounded-full px-1.5 py-0.5 text-[11px] font-medium',
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' => $deltaGood,
        'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400' => ! $deltaGood,
    ])>
        <x-ui.icon :name="$deltaUp ? 'arrow-up-right' : 'arrow-down-right'" class="h-3 w-3" stroke-width="2.5" />
        {{ number_format(abs($deltaPct), 0, ',', ' ')."\u{00A0}%" }}
    </span>
@endif
