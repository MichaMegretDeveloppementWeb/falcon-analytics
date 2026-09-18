@props([
    'labels' => [],
    'values' => [],
    'color' => '--an-series-1',
    'height' => 'an:h-56',
    'event' => 'an-realtime-tick',
    'channel' => 'pulse',
])

{{--
    Polling-friendly area line: same look as the area-chart component, but the
    canvas lives under wire:ignore and refreshes IN PLACE from the page's tick
    event (chart.update('none')), so a poll never destroys and re-animates it.
--}}
<div
    wire:ignore
    class="{{ $height }} an:w-full"
    x-data="anLiveLine({
        labels: @js(array_values($labels)),
        values: @js(array_values($values)),
        color: @js($color),
        channel: @js($channel),
    })"
    x-on:{{ $event }}.window="refresh($event.detail)"
>
    <canvas x-ref="canvas"></canvas>
</div>
