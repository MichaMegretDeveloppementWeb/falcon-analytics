import { doughnutDataset, doughnutOptions } from './chart-parts.js';

/** A doughnut drawn once, from the values the page was rendered with. */
export function anDonut({ labels, values, colors }) {
    let chart = null;

    return {
        async init() {
            await window.falconCharts();

            chart = new window.Chart(this.$refs.canvas, {
                type: 'doughnut',
                data: { labels, datasets: [doughnutDataset(values, colors)] },
                options: doughnutOptions(),
            });
        },

        destroy() {
            chart?.destroy();
            chart = null;
        },
    };
}
