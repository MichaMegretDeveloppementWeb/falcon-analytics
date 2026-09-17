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
        'an:inline-flex an:items-center an:gap-x-0.5 an:rounded-full an:px-1.5 an:py-0.5 an:text-[11px] an:font-medium',
        'an:bg-emerald-50 an:text-emerald-700 an:dark:bg-emerald-500/10 an:dark:text-emerald-400' => $deltaGood,
        'an:bg-red-50 an:text-red-600 an:dark:bg-red-500/10 an:dark:text-red-400' => ! $deltaGood,
    ])>
        <x-ui::icon :name="$deltaUp ? 'arrow-up-right' : 'arrow-down-right'" class="an:h-3 an:w-3" stroke-width="2.5" />
        {{ ($deltaPct > 0 ? '+' : '−').number_format(abs($deltaPct), 0, ',', ' ')."\u{00A0}%" }}
    </span>
@endif
