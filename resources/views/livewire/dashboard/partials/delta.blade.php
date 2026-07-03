@php
    // Params: $current, $previous, optional $inverse (true when lower is better).
    // Arrow follows the value's direction, colour follows whether it is good. An
    // "up from an empty previous period" reads as +100 %. Hidden on a genuine 0 %.
    $deltaInverse = $inverse ?? false;
    $deltaHasBaseline = ((float) $previous) != 0.0;
    $deltaUp = $current > $previous;

    if ($deltaHasBaseline) {
        $deltaPct = (int) round((($current - $previous) / $previous) * 100);
    } else {
        $deltaPct = $current > 0 ? 100 : 0;
        $deltaUp = $deltaPct > 0;
    }

    $deltaGood = $deltaInverse ? ! $deltaUp : $deltaUp;
@endphp

@if ($deltaPct !== 0)
    <span @class([
        'inline-flex items-center gap-x-0.5 rounded-full px-1.5 py-0.5 text-[11px] font-medium',
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' => $deltaGood,
        'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400' => ! $deltaGood,
    ])>
        <x-ui.icon :name="$deltaUp ? 'arrow-up-right' : 'arrow-down-right'" class="h-3 w-3" stroke-width="2.5" />
        {{ ($deltaPct > 0 ? '+' : '−').number_format(abs($deltaPct), 0, ',', ' ')."\u{00A0}%" }}
    </span>
@endif
