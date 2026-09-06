@props([
    'labels' => [],
    'values' => [],
    'color' => '#1684ea',
    'height' => 'h-56',
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
    class="{{ $height }} w-full"
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
                        pointHoverBorderColor: this.isDark ? '#111827' : '#ffffff',
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
                        tooltip: {
                            backgroundColor: this.isDark ? '#1f2937' : '#ffffff',
                            titleColor: this.isDark ? '#f3f4f6' : '#111827',
                            bodyColor: this.isDark ? '#d1d5db' : '#374151',
                            borderColor: this.isDark ? '#374151' : '#e5e7eb',
                            borderWidth: 1,
                            padding: 8,
                            cornerRadius: 6,
                            displayColors: false,
                            bodyFont: { family: 'DM Sans', size: 12 },
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            border: { display: false },
                            ticks: { color: this.muted(), maxTicksLimit: 6, maxRotation: 0, font: { family: 'DM Sans', size: 11 } },
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: this.grid() },
                            border: { display: false },
                            ticks: { color: this.muted(), precision: 0, maxTicksLimit: 4, font: { family: 'DM Sans', size: 11 } },
                        },
                    },
                },
            });

            this.observer = new MutationObserver(() => {
                const dark = document.documentElement.classList.contains('dark');
                if (dark === this.isDark || !this.$el._chart) return;
                this.isDark = dark;
                this.$el._chart.options.scales.x.ticks.color = this.muted();
                this.$el._chart.options.scales.y.ticks.color = this.muted();
                this.$el._chart.options.scales.y.grid.color = this.grid();
                this.$el._chart.update('none');
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
