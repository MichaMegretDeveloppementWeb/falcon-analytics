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
        observer: null,
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
                backgroundColor: (ctx) => {
                    const area = ctx.chart.chartArea;
                    if (!area) return 'transparent';
                    const g = ctx.chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
                    g.addColorStop(0, color + '2b');
                    g.addColorStop(1, color + '00');
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

            {{-- A drawn chart holds its resolved values, so a theme switch has
                 to be read again and written back. --}}
            this.observer = new MutationObserver(() => {
                if (!this.$el._chart) return;
                const c = window.falconChartColors();
                const o = this.$el._chart.options;
                o.scales.x.ticks.color = c.tick;
                o.scales.y.ticks.color = c.tick;
                o.scales.y.grid.color = c.grid;
                if (o.scales.y2) o.scales.y2.ticks.color = c.tick;
                if (o.plugins.legend.labels) o.plugins.legend.labels.color = c.tick;
                Object.assign(o.plugins.tooltip, {
                    backgroundColor: c.tooltipBg,
                    titleColor: c.tooltipTitle,
                    bodyColor: c.tooltipBody,
                    borderColor: c.tooltipBorder,
                });
                for (const dataset of this.$el._chart.data.datasets) {
                    dataset.pointHoverBorderColor = window.falconToken('--ui-bg-surface');
                }
                this.$el._chart.update('none');
            });
            this.observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        },
        destroy() {
            this.observer?.disconnect();
            this.$el._chart?.destroy();
        },
    }"
    {{ $attributes->merge(['class' => 'an:relative '.$height]) }}
>
    <canvas x-ref="canvas"></canvas>
</div>
