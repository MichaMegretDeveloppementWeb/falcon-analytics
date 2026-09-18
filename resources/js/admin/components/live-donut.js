import { doughnutDataset, doughnutOptions, payloadFor, tokenColours } from './chart-parts.js';

/**
 * A doughnut refreshed in place by the page's tick, so a poll never destroys
 * and re-animates it. Its centre figure follows the same payload.
 */
export function anLiveDonut({ labels, values, colors, total, channel }) {
    let chart = null;

    return {
        total,

        async init() {
            await window.falconCharts();

            chart = new window.Chart(this.$refs.canvas, {
                type: 'doughnut',
                data: { labels, datasets: [doughnutDataset(values, colors)] },
                options: doughnutOptions(),
            });
        },

        refresh(detail) {
            const payload = payloadFor(detail, channel);

            if (! payload || ! chart) {
                return;
            }

            this.total = payload.total;
            chart.data.labels = payload.labels;
            chart.data.datasets[0].data = payload.values;
            chart.data.datasets[0].backgroundColor = tokenColours(payload.colors);
            chart.update('none');
        },

        destroy() {
            chart?.destroy();
            chart = null;
        },
    };
}
