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
    x-data="{
        chart: null,
        observer: null,
        {{-- `colors` arrives as token NAMES, never as values · a canvas resolves
             no `var()`, so the page is asked what each name currently holds. --}}
        slices() { return @js(array_values($colors)).map(window.falconToken); },
        async init() {
            {{-- Chart.js loads on demand: the kit ships it as a separate file
                 that pages without a chart never download. --}}
            await window.falconCharts();

            {{-- Nothing about the tooltip is named here. `charts.js` reads the
                 kit's tokens and sets them as Chart.js's defaults, so it comes
                 out looking like the rest of the suite on its own. --}}
            this.chart = new window.Chart(this.$refs.canvas, {
                type: 'doughnut',
                data: {
                    labels: @js(array_values($labels)),
                    datasets: [{
                        data: @js(array_values($values)),
                        backgroundColor: this.slices(),
                        borderColor: window.falconToken('--ui-bg-surface'),
                        borderWidth: 2,
                        hoverOffset: 3,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: {
                        legend: { display: false },
                        tooltip: { padding: 8, cornerRadius: 6, bodyFont: { size: 12 } },
                    },
                },
            });

            {{-- A drawn chart holds its resolved values, so a theme switch has
                 to be read again and written back. --}}
            this.observer = new MutationObserver(() => {
                if (!this.chart) return;
                const c = window.falconChartColors();
                this.chart.data.datasets[0].backgroundColor = this.slices();
                this.chart.data.datasets[0].borderColor = window.falconToken('--ui-bg-surface');
                Object.assign(this.chart.options.plugins.tooltip, {
                    backgroundColor: c.tooltipBg,
                    titleColor: c.tooltipTitle,
                    bodyColor: c.tooltipBody,
                    borderColor: c.tooltipBorder,
                });
                this.chart.update('none');
            });
            this.observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        },
        destroy() {
            this.observer?.disconnect();
            this.chart?.destroy();
        },
    }"
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
