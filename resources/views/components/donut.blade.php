@props([
    'labels' => [],
    'values' => [],
    'colors' => [],
    'total' => '',
    'caption' => null,
    'size' => 'an:h-28 an:w-28',
])

@php
    $totalLength = mb_strlen((string) $total);
    $centerSize = $totalLength >= 8 ? 'an:text-[11px]' : ($totalLength >= 6 ? 'an:text-[13px]' : 'an:text-base');
@endphp

<div
    class="an:relative an:shrink-0 {{ $size }}"
    x-data="anDonut({
        labels: @js(array_values($labels)),
        values: @js(array_values($values)),
        colors: @js(array_values($colors)),
    })"
>
    {{-- Center content sits behind the canvas and shows through the doughnut hole,
         so tooltips (drawn on the canvas) render above it instead of being hidden. --}}
    <canvas x-ref="canvas" class="an:relative an:z-10"></canvas>
    <div class="an:pointer-events-none an:absolute an:inset-0 an:z-0 an:flex an:flex-col an:items-center an:justify-center an:px-2 an:text-center an:leading-tight">
        <span class="{{ $centerSize }} an:font-semibold an:tracking-tight an:text-primary">{{ $total }}</span>
        @if ($caption)
            <span class="an:text-[10px] an:uppercase an:tracking-wide an:text-muted">{{ $caption }}</span>
        @endif
    </div>
</div>
