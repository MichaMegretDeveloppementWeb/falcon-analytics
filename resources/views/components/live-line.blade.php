@props([
    'labels' => [],
    'values' => [],
    'color' => '--an-series-1',
    'height' => 'an:h-56',
    'event' => 'analytics-realtime-tick',
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
    x-data="{
        async init() {
            {{-- Chart.js loads on demand: the kit ships it as a separate file
                 that pages without a chart never download. --}}
            await window.falconCharts();

            {{-- `color` is a token NAME · a canvas resolves no `var()`, so the
                 page is asked what it currently holds. Ticks, grid and tooltip
                 are not named at all: the kit's defaults carry them. --}}
            const color = window.falconToken(@js($color));
            {{-- Chart kept on the DOM node, not in Alpine's reactive state (see area-chart). --}}
            this.$el._chart = new window.Chart(this.$refs.canvas, {
                type: 'line',
                data: {
                    labels: @js(array_values($labels)),
                    datasets: [{
                        data: @js(array_values($values)),
                        borderColor: color,
                        borderWidth: 2.5,
                        tension: 0.4,
                        cubicInterpolationMode: 'monotone',
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        pointHoverBackgroundColor: color,
                        pointHoverBorderColor: window.falconToken('--ui-bg-surface'),
                        pointHoverBorderWidth: 2,
                        fill: true,
                        {{-- Asked for at paint time, so a theme switch needs no
                             help here: the gradient is rebuilt on every update
                             and reads the token it is given. --}}
                        backgroundColor: (c) => {
                            const { ctx, chartArea } = c.chart;
                            if (!chartArea) return 'transparent';
                            const tint = window.falconToken(@js($color));
                            const g = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                            g.addColorStop(0, tint + '33');
                            g.addColorStop(1, tint + '00');
                            return g;
                        },
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: { padding: 8, cornerRadius: 6, displayColors: false, bodyFont: { size: 12 } },
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            border: { display: false },
                            ticks: { maxTicksLimit: 6, maxRotation: 0, font: { size: 11 } },
                        },
                        y: {
                            beginAtZero: true,
                            border: { display: false },
                            ticks: { precision: 0, maxTicksLimit: 4, font: { size: 11 } },
                        },
                    },
                },
            });

        },
        {{-- A drawn chart holds the values it was handed, so a theme switch has
             to hand them over again. **Only what is this component's own** ·
             the graduations, the grid, the tooltip and the legend come from the
             kit's tokens, and the kit repaints them once for the whole page. --}}
        repaint() {
            const chart = this.$el._chart;
            if (!chart) return;
            const color = window.falconToken(@js($color));
            Object.assign(chart.data.datasets[0], {
                borderColor: color,
                pointHoverBackgroundColor: color,
                pointHoverBorderColor: window.falconToken('--ui-bg-surface'),
            });
            chart.update('none');
        },
        refresh(detail) {
            const payload = (Array.isArray(detail) ? detail[0] : detail)?.[@js($channel)];
            if (!payload || !this.$el._chart) return;
            this.$el._chart.data.labels = payload.labels;
            this.$el._chart.data.datasets[0].data = payload.values;
            this.$el._chart.update('none');
        },
        destroy() {
            this.$el._chart?.destroy();
        },
    }"
    x-on:theme-changed.window="repaint()"
    x-on:{{ $event }}.window="refresh($event.detail)"
>
    <canvas x-ref="canvas"></canvas>
</div>
