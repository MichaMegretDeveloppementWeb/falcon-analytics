@props([
    'labels' => [],
    'values' => [],
    'colors' => [],
    'total' => '',
    'caption' => null,
    'size' => 'h-28 w-28',
])

<div
    class="relative shrink-0 {{ $size }}"
    x-data="{
        chart: null,
        isDark: document.documentElement.classList.contains('dark'),
        surface() { return this.isDark ? '#111827' : '#ffffff'; },
        init() {
            this.chart = new window.Chart(this.$refs.canvas, {
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

            new MutationObserver(() => {
                const dark = document.documentElement.classList.contains('dark');
                if (dark === this.isDark || !this.chart) return;
                this.isDark = dark;
                this.chart.data.datasets[0].borderColor = this.surface();
                this.chart.options.plugins.tooltip.backgroundColor = dark ? '#1f2937' : '#ffffff';
                this.chart.options.plugins.tooltip.titleColor = dark ? '#f3f4f6' : '#111827';
                this.chart.options.plugins.tooltip.bodyColor = dark ? '#d1d5db' : '#374151';
                this.chart.options.plugins.tooltip.borderColor = dark ? '#374151' : '#e5e7eb';
                this.chart.update('none');
            }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        },
    }"
>
    <canvas x-ref="canvas"></canvas>
    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
        <span class="text-base font-semibold tracking-tight text-primary">{{ $total }}</span>
        @if ($caption)
            <span class="text-[10px] uppercase tracking-wide text-muted">{{ $caption }}</span>
        @endif
    </div>
</div>
