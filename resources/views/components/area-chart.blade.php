@props([
    'labels' => [],
    'data' => [],
    'label' => '',
    'color' => '#1684ea',
    'height' => 'h-64',
])

{{--
    Area chart tuned for readability: a single smooth line, a soft top-down fill,
    no points, spaced date ticks, a minimal borderless Y axis and a clean tooltip.
    Theme-aware; re-created by Livewire via a wire:key on the wrapper.
--}}
<div
    x-data="{
        chart: null,
        isDark: document.documentElement.classList.contains('dark'),
        muted() { return this.isDark ? '#6b7280' : '#9ca3af'; },
        grid() { return this.isDark ? 'rgba(255,255,255,0.06)' : 'rgba(17,24,39,0.06)'; },
        init() {
            const color = @js($color);
            this.chart = new window.Chart(this.$refs.canvas, {
                type: 'line',
                data: {
                    labels: @js($labels),
                    datasets: [{
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
                        backgroundColor: (ctx) => {
                            const area = ctx.chart.chartArea;
                            if (!area) return 'transparent';
                            const g = ctx.chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
                            g.addColorStop(0, color + '2b');
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
                        tooltip: {
                            backgroundColor: this.isDark ? '#1f2937' : '#ffffff',
                            titleColor: this.isDark ? '#f3f4f6' : '#111827',
                            bodyColor: this.isDark ? '#d1d5db' : '#374151',
                            borderColor: this.isDark ? '#374151' : '#e5e7eb',
                            borderWidth: 1,
                            padding: 10,
                            cornerRadius: 8,
                            displayColors: false,
                            titleFont: { family: 'DM Sans', size: 12, weight: '600' },
                            bodyFont: { family: 'DM Sans', size: 12 },
                        },
                    },
                    scales: {
                        x: {
                            border: { display: false },
                            grid: { display: false },
                            ticks: { maxTicksLimit: 8, maxRotation: 0, autoSkip: true, font: { size: 11, family: 'DM Sans' }, color: this.muted() },
                        },
                        y: {
                            beginAtZero: true,
                            border: { display: false },
                            grid: { color: this.grid(), drawTicks: false },
                            ticks: { maxTicksLimit: 5, padding: 8, precision: 0, font: { size: 11, family: 'DM Sans' }, color: this.muted() },
                        },
                    },
                },
            });

            new MutationObserver(() => {
                const dark = document.documentElement.classList.contains('dark');
                if (dark === this.isDark || !this.chart) return;
                this.isDark = dark;
                const o = this.chart.options;
                o.scales.x.ticks.color = this.muted();
                o.scales.y.ticks.color = this.muted();
                o.scales.y.grid.color = this.grid();
                o.plugins.tooltip.backgroundColor = dark ? '#1f2937' : '#ffffff';
                o.plugins.tooltip.titleColor = dark ? '#f3f4f6' : '#111827';
                o.plugins.tooltip.bodyColor = dark ? '#d1d5db' : '#374151';
                o.plugins.tooltip.borderColor = dark ? '#374151' : '#e5e7eb';
                this.chart.update('none');
            }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        },
    }"
    {{ $attributes->merge(['class' => 'relative '.$height]) }}
>
    <canvas x-ref="canvas"></canvas>
</div>
