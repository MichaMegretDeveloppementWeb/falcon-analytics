@props([
    'labels' => [],
    'data' => [],
    'label' => '',
    'color' => '#1684ea',
    'data2' => [],
    'label2' => '',
    'color2' => '#10b981',
    'height' => 'h-64',
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
        isDark: document.documentElement.classList.contains('dark'),
        muted() { return this.isDark ? '#6b7280' : '#9ca3af'; },
        grid() { return this.isDark ? 'rgba(255,255,255,0.06)' : 'rgba(17,24,39,0.06)'; },
        async init() {
            {{-- Chart.js arrive a la demande · le kit en fait un fichier a part,
                 que les pages sans graphique ne telechargent jamais. Chaque
                 lecteur de `_chart` plus bas garde deja son absence. --}}
            await window.falconCharts();

            const color = @js($color);
            const color2 = @js($color2);
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
                pointHoverBorderColor: this.isDark ? '#111827' : '#ffffff',
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
                    pointHoverBorderColor: this.isDark ? '#111827' : '#ffffff',
                    pointHoverBorderWidth: 2,
                    fill: false,
                    yAxisID: 'y2',
                });
            }
            const scales = {
                x: { border: { display: false }, grid: { display: false }, ticks: { maxTicksLimit: 8, maxRotation: 0, autoSkip: true, font: { size: 11, family: 'DM Sans' }, color: this.muted() } },
                y: { beginAtZero: true, border: { display: false }, grid: { color: this.grid(), drawTicks: false }, ticks: { maxTicksLimit: 5, padding: 8, precision: 0, font: { size: 11, family: 'DM Sans' }, color: this.muted() } },
            };
            if (hasSecond) {
                scales.y2 = { position: 'right', beginAtZero: true, border: { display: false }, grid: { display: false }, ticks: { maxTicksLimit: 5, padding: 8, precision: 0, font: { size: 11, family: 'DM Sans' }, color: this.muted() } };
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
                            ? { display: true, position: 'top', align: 'end', labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true, pointStyle: 'circle', font: { size: 11, family: 'DM Sans' }, color: this.muted() } }
                            : { display: false },
                        tooltip: {
                            backgroundColor: this.isDark ? '#1f2937' : '#ffffff',
                            titleColor: this.isDark ? '#f3f4f6' : '#111827',
                            bodyColor: this.isDark ? '#d1d5db' : '#374151',
                            borderColor: this.isDark ? '#374151' : '#e5e7eb',
                            borderWidth: 1,
                            padding: 10,
                            cornerRadius: 8,
                            displayColors: hasSecond,
                            titleFont: { family: 'DM Sans', size: 12, weight: '600' },
                            bodyFont: { family: 'DM Sans', size: 12 },
                        },
                    },
                    scales,
                },
            });

            this.observer = new MutationObserver(() => {
                const dark = document.documentElement.classList.contains('dark');
                if (dark === this.isDark || !this.$el._chart) return;
                this.isDark = dark;
                const o = this.$el._chart.options;
                o.scales.x.ticks.color = this.muted();
                o.scales.y.ticks.color = this.muted();
                o.scales.y.grid.color = this.grid();
                if (o.scales.y2) o.scales.y2.ticks.color = this.muted();
                if (o.plugins.legend.labels) o.plugins.legend.labels.color = this.muted();
                o.plugins.tooltip.backgroundColor = dark ? '#1f2937' : '#ffffff';
                o.plugins.tooltip.titleColor = dark ? '#f3f4f6' : '#111827';
                o.plugins.tooltip.bodyColor = dark ? '#d1d5db' : '#374151';
                o.plugins.tooltip.borderColor = dark ? '#374151' : '#e5e7eb';
                this.$el._chart.update('none');
            });
            this.observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        },
        destroy() {
            this.observer?.disconnect();
            this.$el._chart?.destroy();
        },
    }"
    {{ $attributes->merge(['class' => 'relative '.$height]) }}
>
    <canvas x-ref="canvas"></canvas>
</div>
