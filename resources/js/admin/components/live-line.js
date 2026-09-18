import { payloadFor, tokenColour } from './chart-parts.js';

/** The line and its fill, in the colour the page named. */
function dataset(values, color) {
    return {
        data: values,
        borderColor: tokenColour(color),
        borderWidth: 2.5,
        tension: 0.4,
        cubicInterpolationMode: 'monotone',
        pointRadius: 0,
        pointHoverRadius: 4,
        pointHoverBackgroundColor: tokenColour(color),
        pointHoverBorderColor: 'var(--ui-bg-surface)',
        pointHoverBorderWidth: 2,
        fill: true,
        backgroundColor: window.falconFade(color, 0.2),
    };
}

/** The options of the live line: no legend, sparse ticks. */
function options() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: { padding: 8, cornerRadius: 6, displayColors: false, bodyFont: { size: 12 } },
        },
        scales: {
            x: {
                grid: { display: false },
                border: { display: false },
                ticks: { maxTicksLimit: 6, maxRotation: 0, font: { size: 11 } },
            },
            y: {
                beginAtZero: true,
                border: { display: false },
                ticks: { precision: 0, maxTicksLimit: 4, font: { size: 11 } },
            },
        },
    };
}

/** An area line refreshed in place by the page's tick. */
export function anLiveLine({ labels, values, color, channel }) {
    let chart = null;

    return {
        async init() {
            await window.falconCharts();

            chart = new window.Chart(this.$refs.canvas, {
                type: 'line',
                data: { labels, datasets: [dataset(values, color)] },
                options: options(),
            });
        },

        refresh(detail) {
            const payload = payloadFor(detail, channel);

            if (! payload || ! chart) {
                return;
            }

            chart.data.labels = payload.labels;
            chart.data.datasets[0].data = payload.values;
            chart.update('none');
        },

        destroy() {
            chart?.destroy();
            chart = null;
        },
    };
}
