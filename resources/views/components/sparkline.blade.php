@props([
    'values' => [],
    'color' => '--an-series-1',
    'height' => 'an:h-8',
])

<div
    class="{{ $height }} an:w-full"
    x-data="{
        async init() {
            {{-- Chart.js loads on demand: the kit ships it as a separate file
                 that pages without a chart never download. --}}
            await window.falconCharts();

            {{-- `color` is a token NAME · a canvas resolves no `var()`, so the
                 page is asked what it currently holds. A sparkline draws no
                 axis and no tooltip, so its line is the only thing it has to
                 ask for. --}}
            const color = window.falconToken(@js($color));
            {{-- Chart kept on the DOM node, not in Alpine's reactive state (see area-chart). --}}
            this.$el._chart = new window.Chart(this.$refs.canvas, {
                type: 'line',
                data: {
                    labels: @js(array_keys(array_values($values))),
                    datasets: [{
                        data: @js(array_values($values)),
                        borderColor: color,
                        borderWidth: 1.5,
                        tension: 0.4,
                        cubicInterpolationMode: 'monotone',
                        pointRadius: 0,
                        fill: true,
                        {{-- Asked for at paint time, so a theme switch needs no
                             help here: the gradient is rebuilt on every update. --}}
                        backgroundColor: (c) => {
                            const { ctx, chartArea } = c.chart;
                            if (!chartArea) return 'transparent';
                            const tint = window.falconToken(@js($color));
                            const g = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                            g.addColorStop(0, tint + '26');
                            g.addColorStop(1, tint + '00');
                            return g;
                        },
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    plugins: { legend: { display: false }, tooltip: { enabled: false } },
                    scales: { x: { display: false }, y: { display: false, min: 0 } },
                    elements: { line: { borderJoinStyle: 'round' } },
                },
            });
        },
        {{-- A drawn chart holds the values it was handed, so a theme switch has
             to hand them over again. It had nothing at all here until
             2026-09-13, on the ground that its parent redrew it whole — which
             the parent does on a refresh of its figures, and never on a theme
             switch. The line stayed in the colour of the theme before. --}}
        repaint() {
            const chart = this.$el._chart;
            if (!chart) return;
            chart.data.datasets[0].borderColor = window.falconToken(@js($color));
            chart.update('none');
        },
        destroy() {
            this.$el._chart?.destroy();
        },
    }"
    x-on:theme-changed.window="repaint()"
>
    <canvas x-ref="canvas"></canvas>
</div>
