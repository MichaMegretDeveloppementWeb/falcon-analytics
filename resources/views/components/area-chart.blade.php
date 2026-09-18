@props([
    'labels' => [],
    'data' => [],
    'label' => '',
    'color' => '--an-series-1',
    'data2' => [],
    'label2' => '',
    'color2' => '--an-conversion',
    'height' => 'an:h-64',
])

{{--
    Area chart tuned for readability: a smooth line with a soft top-down fill, no
    points, spaced date ticks, a minimal borderless Y axis and a clean tooltip.
    An optional second series (data2) draws as a line on a right-hand axis with a
    legend. Theme-aware; re-created by Livewire via a wire:key on the wrapper.
--}}
<div
    x-data="anAreaChart({
        labels: @js($labels),
        data: @js($data),
        label: @js($label),
        color: @js($color),
        data2: @js(array_values($data2)),
        label2: @js($label2),
        color2: @js($color2),
    })"
    {{ $attributes->merge(['class' => 'an:relative '.$height]) }}
>
    <canvas x-ref="canvas"></canvas>
</div>
