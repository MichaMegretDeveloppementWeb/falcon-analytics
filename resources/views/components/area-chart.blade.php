@props([
    'labels' => [],
    'data' => [],
    'label' => '',
    'color' => '--an-series-1',
    'data2' => [],
    'label2' => '',
    'color2' => '--an-conversion',
    'height' => 'an:h-64',
])

{{--
    Area chart tuned for readability: a smooth line with a soft top-down fill, no
    points, spaced date ticks, a minimal borderless Y axis and a clean tooltip.
    An optional second series (data2) draws as a line on a right-hand axis with a
    legend. Theme-aware; re-created by Livewire via a wire:key on the wrapper.
--}}
<div
    x-data="{
        async init() {
            {{-- Chart.js loads on demand: the kit ships it as a separate file
                 that pages without a chart never download. --}}
            await window.falconCharts();

            {{-- Both colours are token NAMES · a canvas resolves no `var()`, so
                 the page is asked what they currently hold. Ticks, grid and
                 tooltip are not named at all: the kit's defaults carry them. --}}
            const color = window.falconToken(@js($color));
            const color2 = window.falconToken(@js($color2));
            const data2 = @js(array_values($data2));
            const hasSecond = data2.length > 0;
            {{-- The Chart instance lives on the DOM node, not in Alpine's reactive
                 state: its circular refs would become a reactive proxy and make
                 Livewire's toRaw recurse to a stack overflow on update. --}}
            const datasets = [{
                label: @js($label),
                data: @js($data),
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
                yAxisID: 'y',
                {{-- Asked for at paint time, so a theme switch needs no help
                     here: the gradient is rebuilt on every update. --}}
                backgroundColor: (ctx) => {
                    const area = ctx.chart.chartArea;
                    if (!area) return 'transparent';
                    const tint = window.falconToken(@js($color));
                    const g = ctx.chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
                    g.addColorStop(0, tint + '2b');
                    g.addColorStop(1, tint + '00');
                    return g;
                },
            }];
            if (hasSecond) {
                datasets.push({
                    label: @js($label2),
                    data: data2,
                    borderColor: color2,
                    borderWidth: 2,
                    tension: 0.4,
                    cubicInterpolationMode: 'monotone',
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    pointHoverBackgroundColor: color2,
                    pointHoverBorderColor: window.falconToken('--ui-bg-surface'),
                    pointHoverBorderWidth: 2,
                    fill: false,
                    yAxisID: 'y2',
                });
            }
            // No font and no colour are named here or below: the kit reads the
            // page's and sets them as Chart.js's defaults, and naming one would
            // freeze it against a host's theme.
            const scales = {
                x: { border: { display: false }, grid: { display: false }, ticks: { maxTicksLimit: 8, maxRotation: 0, autoSkip: true, font: { size: 11 } } },
                y: { beginAtZero: true, border: { display: false }, grid: { drawTicks: false }, ticks: { maxTicksLimit: 5, padding: 8, precision: 0, font: { size: 11 } } },
            };
            if (hasSecond) {
                scales.y2 = { position: 'right', beginAtZero: true, border: { display: false }, grid: { display: false }, ticks: { maxTicksLimit: 5, padding: 8, precision: 0, font: { size: 11 } } };
            }
            this.$el._chart = new window.Chart(this.$refs.canvas, {
                type: 'line',
                data: { labels: @js($labels), datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 400 },
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: hasSecond
                            ? { display: true, position: 'top', align: 'end', labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true, pointStyle: 'circle', font: { size: 11 } } }
                            : { display: false },
                        tooltip: {
                            padding: 10,
                            displayColors: hasSecond,
                            titleFont: { size: 12, weight: '600' },
                            bodyFont: { size: 12 },
                        },
                    },
                    scales,
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
            const surface = window.falconToken('--ui-bg-surface');
            const colors = [window.falconToken(@js($color)), window.falconToken(@js($color2))];
            chart.data.datasets.forEach((dataset, index) => {
                Object.assign(dataset, {
                    borderColor: colors[index],
                    pointHoverBackgroundColor: colors[index],
                    pointHoverBorderColor: surface,
                });
            });
            chart.update('none');
        },
        destroy() {
            this.$el._chart?.destroy();
        },
    }"
    x-on:theme-changed.window="repaint()"
    {{ $attributes->merge(['class' => 'an:relative '.$height]) }}
>
    <canvas x-ref="canvas"></canvas>
</div>
