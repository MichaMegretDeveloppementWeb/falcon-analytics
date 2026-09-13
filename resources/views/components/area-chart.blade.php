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

            {{-- Both colours are token NAMES, written as the page would write
                 them · the kit turns them into the values of the theme in
                 force, before every draw and therefore after every switch.
                 Ticks, grid and tooltip are not named at all: the kit's
                 defaults carry them. --}}
            const data2 = @js(array_values($data2));
            const hasSecond = data2.length > 0;
            {{-- The Chart instance lives on the DOM node, not in Alpine's reactive
                 state: its circular refs would become a reactive proxy and make
                 Livewire's toRaw recurse to a stack overflow on update. --}}
            const datasets = [{
                label: @js($label),
                data: @js($data),
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
                yAxisID: 'y',
                backgroundColor: window.falconFade(@js($color), 0.17),
            }];
            if (hasSecond) {
                datasets.push({
                    label: @js($label2),
                    data: data2,
                    borderColor: 'var({{ $color2 }})',
                    borderWidth: 2,
                    tension: 0.4,
                    cubicInterpolationMode: 'monotone',
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    pointHoverBackgroundColor: 'var({{ $color2 }})',
                    pointHoverBorderColor: 'var(--ui-bg-surface)',
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
        destroy() {
            this.$el._chart?.destroy();
        },
    }"
    {{ $attributes->merge(['class' => 'an:relative '.$height]) }}
>
    <canvas x-ref="canvas"></canvas>
</div>
