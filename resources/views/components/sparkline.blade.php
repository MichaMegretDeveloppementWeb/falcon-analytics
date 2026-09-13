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

            {{-- `color` is a token NAME, written as the page would write it ·
                 the kit turns it into the value of the theme in force, before
                 every draw and therefore after every theme switch. Nothing here
                 knows which theme is on, and that is the point. --}}
            {{-- Chart kept on the DOM node, not in Alpine's reactive state (see area-chart). --}}
            this.$el._chart = new window.Chart(this.$refs.canvas, {
                type: 'line',
                data: {
                    labels: @js(array_keys(array_values($values))),
                    datasets: [{
                        data: @js(array_values($values)),
                        borderColor: 'var({{ $color }})',
                        borderWidth: 1.5,
                        tension: 0.4,
                        cubicInterpolationMode: 'monotone',
                        pointRadius: 0,
                        fill: true,
                        backgroundColor: window.falconFade(@js($color), 0.15),
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
