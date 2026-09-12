@props([
    'labels' => [],
    'values' => [],
    'colors' => [],
    'total' => '',
    'caption' => null,
    'size' => 'an:h-28 an:w-28',
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
    class="an:relative an:shrink-0 {{ $size }}"
    x-data="{
        observer: null,
        total: @js((string) $total),
        {{-- Token NAMES in, values out · a canvas resolves no `var()`, so the
             page is asked what each name currently holds. Used both at first
             draw and on every live refresh, the names travelling with the
             payload just as they came from the view. --}}
        resolve(names) { return (names ?? []).map(window.falconToken); },
        async init() {
            {{-- Chart.js loads on demand: the kit ships it as a separate file
                 that pages without a chart never download. --}}
            await window.falconCharts();

            {{-- Chart kept on the DOM node, not in Alpine's reactive state (see area-chart).
                 Nothing about the tooltip is named: the kit's defaults carry it. --}}
            this.$el._chart = new window.Chart(this.$refs.canvas, {
                type: 'doughnut',
                data: {
                    labels: @js(array_values($labels)),
                    datasets: [{
                        data: @js(array_values($values)),
                        backgroundColor: this.resolve(@js(array_values($colors))),
                        borderColor: window.falconToken('--ui-bg-surface'),
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
                        tooltip: { padding: 8, cornerRadius: 6, bodyFont: { size: 12 } },
                    },
                },
            });

            {{-- A drawn chart holds its resolved values, so a theme switch has
                 to be read again and written back. --}}
            this.observer = new MutationObserver(() => {
                if (!this.$el._chart) return;
                const c = window.falconChartColors();
                const dataset = this.$el._chart.data.datasets[0];
                dataset.backgroundColor = this.resolve(this.$el._names);
                dataset.borderColor = window.falconToken('--ui-bg-surface');
                Object.assign(this.$el._chart.options.plugins.tooltip, {
                    backgroundColor: c.tooltipBg,
                    titleColor: c.tooltipTitle,
                    bodyColor: c.tooltipBody,
                    borderColor: c.tooltipBorder,
                });
                this.$el._chart.update('none');
            });
            this.observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

            {{-- The names the last payload brought, kept beside the chart so a
                 theme switch can resolve them again. On the DOM node and not in
                 Alpine's state, for the same reason the chart is. --}}
            this.$el._names = @js(array_values($colors));
        },
        refresh(detail) {
            const payload = (Array.isArray(detail) ? detail[0] : detail)?.[@js($channel)];
            if (!payload || !this.$el._chart) return;
            this.total = payload.total;
            this.$el._names = payload.colors;
            this.$el._chart.data.labels = payload.labels;
            this.$el._chart.data.datasets[0].data = payload.values;
            this.$el._chart.data.datasets[0].backgroundColor = this.resolve(payload.colors);
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
    <canvas x-ref="canvas" class="an:relative an:z-10"></canvas>
    <div class="an:pointer-events-none an:absolute an:inset-0 an:z-0 an:flex an:flex-col an:items-center an:justify-center an:px-2 an:text-center an:leading-tight">
        <span class="an:text-base an:font-semibold an:tracking-tight an:text-primary" x-text="total">{{ $total }}</span>
        @if ($caption)
            <span class="an:text-[10px] an:uppercase an:tracking-wide an:text-muted">{{ $caption }}</span>
        @endif
    </div>
</div>
