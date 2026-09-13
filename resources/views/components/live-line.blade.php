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

            {{-- `color` is a token NAME, written as the page would write it ·
                 the kit turns it into the value of the theme in force, before
                 every draw and therefore after every switch. Ticks, grid and
                 tooltip are not named at all: the kit's defaults carry them. --}}
            {{-- Chart kept on the DOM node, not in Alpine's reactive state (see area-chart). --}}
            this.$el._chart = new window.Chart(this.$refs.canvas, {
                type: 'line',
                data: {
                    labels: @js(array_values($labels)),
                    datasets: [{
                        data: @js(array_values($values)),
                        borderColor: 'var({{ $color }})',
                        borderWidth: 2.5,
                        tension: 0.4,
                        cubicInterpolationMode: 'monotone',
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        pointHoverBackgroundColor: 'var({{ $color }})',
                        pointHoverBorderColor: 'var(--ui-bg-surface)',
                        pointHoverBorderWidth: 2,
                        fill: true,
                        backgroundColor: window.falconFade(@js($color), 0.2),
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
    x-on:{{ $event }}.window="refresh($event.detail)"
>
    <canvas x-ref="canvas"></canvas>
</div>
