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
        observer: null,
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
                        backgroundColor: (c) => {
                            const { ctx, chartArea } = c.chart;
                            if (!chartArea) return 'transparent';
                            const g = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                            g.addColorStop(0, color + '33');
                            g.addColorStop(1, color + '00');
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

            {{-- A drawn chart holds its resolved values, so a theme switch has
                 to be read again and written back. --}}
            this.observer = new MutationObserver(() => {
                if (!this.$el._chart) return;
                const c = window.falconChartColors();
                const chart = this.$el._chart;
                chart.options.scales.x.ticks.color = c.tick;
                chart.options.scales.y.ticks.color = c.tick;
                chart.options.scales.y.grid.color = c.grid;
                chart.data.datasets[0].pointHoverBorderColor = window.falconToken('--ui-bg-surface');
                Object.assign(chart.options.plugins.tooltip, {
                    backgroundColor: c.tooltipBg,
                    titleColor: c.tooltipTitle,
                    bodyColor: c.tooltipBody,
                    borderColor: c.tooltipBorder,
                });
                chart.update('none');
            });
            this.observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        },
        refresh(detail) {
            const payload = (Array.isArray(detail) ? detail[0] : detail)?.[@js($channel)];
            if (!payload || !this.$el._chart) return;
            this.$el._chart.data.labels = payload.labels;
            this.$el._chart.data.datasets[0].data = payload.values;
            this.$el._chart.update('none');
        },
        destroy() {
            this.observer?.disconnect();
            this.$el._chart?.destroy();
        },
    }"
    x-on:{{ $event }}.window="refresh($event.detail)"
>
    <canvas x-ref="canvas"></canvas>
</div>
