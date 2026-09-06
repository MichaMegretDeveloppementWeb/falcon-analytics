@props([
    'labels' => [],
    'values' => [],
    'colors' => [],
    'total' => '',
    'caption' => null,
    'size' => 'h-28 w-28',
    'event' => 'analytics-realtime-tick',
    'channel' => 'devices',
])

{{--
    Polling-friendly doughnut: same look as the donut component, but the canvas
    and its centre figure live under wire:ignore and refresh IN PLACE from the
    page's tick event, so a poll never destroys and re-animates the chart.
--}}
<div
    wire:ignore
    class="relative shrink-0 {{ $size }}"
    x-data="{
        observer: null,
        total: @js((string) $total),
        isDark: document.documentElement.classList.contains('dark'),
        surface() { return this.isDark ? '#111827' : '#ffffff'; },
        async init() {
            {{-- Chart.js arrive a la demande · le kit en fait un fichier a part,
                 que les pages sans graphique ne telechargent jamais. Chaque
                 lecteur de `_chart` plus bas garde deja son absence. --}}
            await window.falconCharts();

            {{-- Chart kept on the DOM node, not in Alpine's reactive state (see area-chart). --}}
            this.$el._chart = new window.Chart(this.$refs.canvas, {
                type: 'doughnut',
                data: {
                    labels: @js(array_values($labels)),
                    datasets: [{
                        data: @js(array_values($values)),
                        backgroundColor: @js(array_values($colors)),
                        borderColor: this.surface(),
                        borderWidth: 2,
                        hoverOffset: 3,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
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
                            bodyFont: { family: 'DM Sans', size: 12 },
                        },
                    },
                },
            });

            this.observer = new MutationObserver(() => {
                const dark = document.documentElement.classList.contains('dark');
                if (dark === this.isDark || !this.$el._chart) return;
                this.isDark = dark;
                this.$el._chart.data.datasets[0].borderColor = this.surface();
                this.$el._chart.options.plugins.tooltip.backgroundColor = dark ? '#1f2937' : '#ffffff';
                this.$el._chart.options.plugins.tooltip.titleColor = dark ? '#f3f4f6' : '#111827';
                this.$el._chart.options.plugins.tooltip.bodyColor = dark ? '#d1d5db' : '#374151';
                this.$el._chart.options.plugins.tooltip.borderColor = dark ? '#374151' : '#e5e7eb';
                this.$el._chart.update('none');
            });
            this.observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        },
        refresh(detail) {
            const payload = (Array.isArray(detail) ? detail[0] : detail)?.[@js($channel)];
            if (!payload || !this.$el._chart) return;
            this.total = payload.total;
            this.$el._chart.data.labels = payload.labels;
            this.$el._chart.data.datasets[0].data = payload.values;
            this.$el._chart.data.datasets[0].backgroundColor = payload.colors;
            this.$el._chart.update('none');
        },
        destroy() {
            this.observer?.disconnect();
            this.$el._chart?.destroy();
        },
    }"
    x-on:{{ $event }}.window="refresh($event.detail)"
>
    {{-- Center content sits behind the canvas and shows through the doughnut hole,
         so tooltips (drawn on the canvas) render above it instead of being hidden. --}}
    <canvas x-ref="canvas" class="relative z-10"></canvas>
    <div class="pointer-events-none absolute inset-0 z-0 flex flex-col items-center justify-center px-2 text-center leading-tight">
        <span class="text-base font-semibold tracking-tight text-primary" x-text="total">{{ $total }}</span>
        @if ($caption)
            <span class="text-[10px] uppercase tracking-wide text-muted">{{ $caption }}</span>
        @endif
    </div>
</div>
