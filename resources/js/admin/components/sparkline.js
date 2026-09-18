import { tokenColour } from './chart-parts.js';

/** A small trend line without axes, legend or tooltip. */
export function anSparkline({ values, color }) {
    let chart = null;

    return {
        async init() {
            await window.falconCharts();

            chart = new window.Chart(this.$refs.canvas, {
                type: 'line',
                data: {
                    labels: values.map((_value, index) => index),
                    datasets: [{
                        data: values,
                        borderColor: tokenColour(color),
                        borderWidth: 1.5,
                        tension: 0.4,
                        cubicInterpolationMode: 'monotone',
                        pointRadius: 0,
                        fill: true,
                        backgroundColor: window.falconFade(color, 0.15),
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    plugins: { legend: { display: false }, tooltip: { enabled: false } },
                    scales: { x: { display: false }, y: { display: false, min: 0 } },
                    elements: { line: { borderJoinStyle: 'round' } },
                },
            });
        },

        destroy() {
            chart?.destroy();
            chart = null;
        },
    };
}
