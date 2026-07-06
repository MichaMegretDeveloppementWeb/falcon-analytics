@props([
    'values' => [],
    'color' => '#1684ea',
    'height' => 'h-8',
])

<div
    class="{{ $height }} w-full"
    x-data="{
        init() {
            const color = @js($color);
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
                        backgroundColor: (c) => {
                            const { ctx, chartArea } = c.chart;
                            if (!chartArea) return 'transparent';
                            const g = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                            g.addColorStop(0, color + '26');
                            g.addColorStop(1, color + '00');
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
        destroy() {
            this.$el._chart?.destroy();
        },
    }"
>
    <canvas x-ref="canvas"></canvas>
</div>
