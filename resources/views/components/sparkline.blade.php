@props([
    'values' => [],
    'color' => '--an-series-1',
    'height' => 'an:h-8',
])

<div
    class="{{ $height }} an:w-full"
    x-data="anSparkline({
        values: @js(array_values($values)),
        color: @js($color),
    })"
>
    <canvas x-ref="canvas"></canvas>
</div>
