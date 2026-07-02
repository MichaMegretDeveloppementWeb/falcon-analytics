@php
    // Params: $current, $previous, optional $inverse (true when lower is better).
    $deltaInverse = $inverse ?? false;
    $deltaHasBaseline = ((float) $previous) != 0.0;
    $deltaUp = $current > $previous;
    $deltaGood = $deltaInverse ? ! $deltaUp : $deltaUp;
    $deltaPct = $deltaHasBaseline ? round((($current - $previous) / $previous) * 100, 1) : 0.0;
    // Without a baseline, an "up from zero" is an infinite rise; show it rather than nothing.
    $deltaShow = $deltaHasBaseline ? ($deltaPct != 0.0) : ((float) $current > 0.0);
    $deltaText = $deltaHasBaseline
        ? (($deltaUp ? '+' : '').number_format($deltaPct, 0, ',', ' ').' %')
        : '+∞ %';
@endphp

@if ($deltaShow)
    <span @class([
        'inline-flex items-center gap-x-0.5 rounded-full px-1.5 py-0.5 text-[11px] font-medium',
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' => $deltaGood,
        'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400' => ! $deltaGood,
    ])>
        <x-ui.icon :name="$deltaUp ? 'arrow-up-right' : 'arrow-down-right'" class="h-3 w-3" stroke-width="2.5" />
        {{ $deltaText }}
    </span>
@endif
