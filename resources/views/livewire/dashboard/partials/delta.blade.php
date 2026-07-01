@php
    // Params: $current, $previous, optional $inverse (true when lower is better).
    $deltaInverse = $inverse ?? false;
    $deltaHasBaseline = ((float) $previous) != 0.0;
    $deltaPct = $deltaHasBaseline ? round((($current - $previous) / $previous) * 100, 1) : 0.0;
    $deltaUp = $current > $previous;
    $deltaGood = $deltaInverse ? ! $deltaUp : $deltaUp;
@endphp

@if ($deltaHasBaseline && $deltaPct != 0.0)
    <span @class([
        'inline-flex items-center gap-x-0.5 rounded-full px-1.5 py-0.5 text-[11px] font-medium',
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' => $deltaGood,
        'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400' => ! $deltaGood,
    ])>
        <x-ui.icon :name="$deltaUp ? 'arrow-up-right' : 'arrow-down-right'" class="h-3 w-3" stroke-width="2.5" />
        {{ $deltaUp ? '+' : '' }}{{ number_format($deltaPct, 0, ',', ' ') }} %
    </span>
@endif
