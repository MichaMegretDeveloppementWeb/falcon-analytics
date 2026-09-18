import { tokenColour } from './chart-parts.js';

const TICK_FONT = { size: 11 };

/** The main series: a smooth line over a soft fill, on the left axis. */
function mainDataset({ data, label, color }) {
    return {
        label,
        data,
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
        yAxisID: 'y',
        backgroundColor: window.falconFade(color, 0.17),
    };
}

/** The optional second series: a line without fill, on the right axis. */
function secondDataset({ data2, label2, color2 }) {
    return {
        label: label2,
        data: data2,
        borderColor: tokenColour(color2),
        borderWidth: 2,
        tension: 0.4,
        cubicInterpolationMode: 'monotone',
        pointRadius: 0,
        pointHoverRadius: 4,
        pointHoverBackgroundColor: tokenColour(color2),
        pointHoverBorderColor: 'var(--ui-bg-surface)',
        pointHoverBorderWidth: 2,
        fill: false,
        yAxisID: 'y2',
    };
}

/** The axes, a right-hand one only when a second series is drawn. */
function scalesFor(hasSecond) {
    const scales = {
        x: { border: { display: false }, grid: { display: false }, ticks: { maxTicksLimit: 8, maxRotation: 0, autoSkip: true, font: TICK_FONT } },
        y: { beginAtZero: true, border: { display: false }, grid: { drawTicks: false }, ticks: { maxTicksLimit: 5, padding: 8, precision: 0, font: TICK_FONT } },
    };

    if (hasSecond) {
        scales.y2 = { position: 'right', beginAtZero: true, border: { display: false }, grid: { display: false }, ticks: { maxTicksLimit: 5, padding: 8, precision: 0, font: TICK_FONT } };
    }

    return scales;
}

/** The chart's options, a legend only when there is something to tell apart. */
function optionsFor(hasSecond) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 400 },
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: hasSecond
                ? { display: true, position: 'top', align: 'end', labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true, pointStyle: 'circle', font: TICK_FONT } }
                : { display: false },
            tooltip: {
                padding: 10,
                displayColors: hasSecond,
                titleFont: { size: 12, weight: '600' },
                bodyFont: { size: 12 },
            },
        },
        scales: scalesFor(hasSecond),
    };
}

/**
 * The dashboard's area chart, with an optional second series.
 *
 * The Chart.js instance is kept outside the component's reactive state: made
 * reactive, its circular references send Livewire's comparison into endless
 * recursion on the next update.
 */
export function anAreaChart(series) {
    let chart = null;

    return {
        async init() {
            await window.falconCharts();

            const hasSecond = series.data2.length > 0;
            const datasets = hasSecond ? [mainDataset(series), secondDataset(series)] : [mainDataset(series)];

            chart = new window.Chart(this.$refs.canvas, {
                type: 'line',
                data: { labels: series.labels, datasets },
                options: optionsFor(hasSecond),
            });
        },

        destroy() {
            chart?.destroy();
            chart = null;
        },
    };
}
