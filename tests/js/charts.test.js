import { afterEach, beforeEach, describe, expect, test } from 'vitest';
import { anAreaChart } from '../../resources/js/admin/components/area-chart.js';
import { doughnutDataset, payloadFor, tokenColour, tokenColours } from '../../resources/js/admin/components/chart-parts.js';
import { anDonut } from '../../resources/js/admin/components/donut.js';
import { anLiveDonut } from '../../resources/js/admin/components/live-donut.js';
import { anLiveLine } from '../../resources/js/admin/components/live-line.js';
import { anSparkline } from '../../resources/js/admin/components/sparkline.js';

/*
 * The dashboard's charts, run against a stand-in Chart.js that records what it
 * is handed. What is under test is what each component asks of Chart.js: the
 * names it gives the colours, the data it draws, the refresh it applies, and
 * the instance it releases.
 */

let drawn;

/** A stand-in for the kit's Chart.js, and for the two helpers the kit offers. */
function standIn() {
    drawn = [];

    window.Chart = class {
        constructor(canvas, config) {
            this.canvas = canvas;
            this.config = config;
            this.data = config.data;
            this.updates = [];
            this.destroyed = false;
            drawn.push(this);
        }

        update(mode) {
            this.updates.push(mode);
        }

        destroy() {
            this.destroyed = true;
        }
    };

    window.falconCharts = async () => {};
    window.falconFade = (name, opacity) => `fade(${name}, ${opacity})`;
}

/** A component as Alpine would hold it: its refs set, ready to initialise. */
function mounted(component) {
    component.$refs = { canvas: document.createElement('canvas') };

    return component;
}

beforeEach(standIn);

afterEach(() => {
    delete window.Chart;
    delete window.falconCharts;
    delete window.falconFade;
});

describe('colours are named, never written', () => {
    test('a token name becomes a CSS variable the kit resolves', () => {
        expect(tokenColour('--an-series-1')).toBe('var(--an-series-1)');
        expect(tokenColours(['--a', '--b'])).toEqual(['var(--a)', 'var(--b)']);
        expect(tokenColours(null)).toEqual([]);
    });

    test('a doughnut names every slice', () => {
        expect(doughnutDataset([3, 4], ['--a', '--b']).backgroundColor).toEqual(['var(--a)', 'var(--b)']);
    });
});

describe('a live tick reaches the chart it is addressed to', () => {
    test('whether Livewire hands an object or a list', () => {
        const payload = { labels: [], values: [] };

        expect(payloadFor({ pulse: payload }, 'pulse')).toBe(payload);
        expect(payloadFor([{ pulse: payload }], 'pulse')).toBe(payload);
        expect(payloadFor({ map: payload }, 'pulse')).toBeUndefined();
        expect(payloadFor(null, 'pulse')).toBeUndefined();
    });
});

describe('the area chart', () => {
    const series = {
        labels: ['1', '2'],
        data: [4, 5],
        label: 'Sessions',
        color: '--an-series-1',
        data2: [],
        label2: 'Conversions',
        color2: '--an-conversion',
    };

    test('draws one series on one axis', async () => {
        await mounted(anAreaChart(series)).init();

        const [chart] = drawn;

        expect(chart.config.data.labels).toEqual(['1', '2']);
        expect(chart.config.data.datasets).toHaveLength(1);
        expect(chart.config.data.datasets[0].borderColor).toBe('var(--an-series-1)');
        expect(chart.config.data.datasets[0].backgroundColor).toBe('fade(--an-series-1, 0.17)');
        expect(chart.config.options.scales.y2).toBeUndefined();
        expect(chart.config.options.plugins.legend.display).toBe(false);
    });

    test('adds a second series on a right-hand axis, with a legend', async () => {
        await mounted(anAreaChart({ ...series, data2: [1, 2] })).init();

        const [chart] = drawn;

        expect(chart.config.data.datasets).toHaveLength(2);
        expect(chart.config.data.datasets[1].yAxisID).toBe('y2');
        expect(chart.config.data.datasets[1].borderColor).toBe('var(--an-conversion)');
        expect(chart.config.options.scales.y2.position).toBe('right');
        expect(chart.config.options.plugins.legend.display).toBe(true);
    });

    test('releases its chart when removed', async () => {
        const component = mounted(anAreaChart(series));

        await component.init();
        component.destroy();

        expect(drawn[0].destroyed).toBe(true);
    });
});

describe('the doughnut', () => {
    test('draws its slices and releases its chart', async () => {
        const component = mounted(anDonut({ labels: ['a', 'b'], values: [1, 2], colors: ['--a', '--b'] }));

        await component.init();
        component.destroy();

        expect(drawn[0].config.type).toBe('doughnut');
        expect(drawn[0].config.data.datasets[0].data).toEqual([1, 2]);
        expect(drawn[0].destroyed).toBe(true);
    });
});

describe('the live doughnut', () => {
    const initial = { labels: ['a'], values: [1], colors: ['--a'], total: '1', channel: 'devices' };

    test('refreshes in place, centre figure included', async () => {
        const component = mounted(anLiveDonut(initial));

        await component.init();
        component.refresh({ devices: { labels: ['b', 'c'], values: [2, 3], colors: ['--b', '--c'], total: '5' } });

        expect(drawn).toHaveLength(1);
        expect(component.total).toBe('5');
        expect(drawn[0].data.labels).toEqual(['b', 'c']);
        expect(drawn[0].data.datasets[0].backgroundColor).toEqual(['var(--b)', 'var(--c)']);
        expect(drawn[0].updates).toEqual(['none']);
    });

    test('ignores a tick addressed to another chart', async () => {
        const component = mounted(anLiveDonut(initial));

        await component.init();
        component.refresh({ pulse: { labels: [], values: [] } });

        expect(component.total).toBe('1');
        expect(drawn[0].updates).toEqual([]);
    });

    test('ignores a tick that arrives before its chart', () => {
        const component = mounted(anLiveDonut(initial));

        expect(() => component.refresh({ devices: { labels: [], values: [], colors: [], total: '0' } })).not.toThrow();
        expect(component.total).toBe('1');
    });
});

describe('the live line', () => {
    test('refreshes in place and releases its chart', async () => {
        const component = mounted(anLiveLine({ labels: ['1'], values: [1], color: '--an-accent', channel: 'pulse' }));

        await component.init();
        component.refresh([{ pulse: { labels: ['1', '2'], values: [1, 2] } }]);
        component.destroy();

        expect(drawn[0].data.labels).toEqual(['1', '2']);
        expect(drawn[0].data.datasets[0].data).toEqual([1, 2]);
        expect(drawn[0].data.datasets[0].borderColor).toBe('var(--an-accent)');
        expect(drawn[0].updates).toEqual(['none']);
        expect(drawn[0].destroyed).toBe(true);
    });
});

describe('the sparkline', () => {
    test('labels its points by position, and draws no axis', async () => {
        await mounted(anSparkline({ values: [3, 1, 4], color: '--an-conversion' })).init();

        expect(drawn[0].config.data.labels).toEqual([0, 1, 2]);
        expect(drawn[0].config.options.scales.x.display).toBe(false);
        expect(drawn[0].config.data.datasets[0].backgroundColor).toBe('fade(--an-conversion, 0.15)');
    });
});
