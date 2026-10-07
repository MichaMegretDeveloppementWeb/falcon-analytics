import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { beforeAll, beforeEach, expect, test, vi } from 'vitest';

/*
 * The page's sealed context goes with every batch, whichever way the batch
 * leaves, and it is read at each send: a page swapped without a reload brings
 * its own, and a page with none sends none.
 */

const source = readFileSync(join(import.meta.dirname, '../../resources/js/collector.js'), 'utf8');

/** What the beacon was handed, in order: each body as the server reads it. */
const beaconed = [];

/** Whether the beacon accepts the next batch · refused, the batch goes by fetch. */
let beaconAccepts = true;

beforeAll(() => {
    vi.useFakeTimers();

    window.__falconAnalytics = { endpoint: '/analytics/collect', route: 'home', context: 'scellé-1' };
    globalThis.fetch = vi.fn(() => Promise.resolve());

    Object.defineProperty(navigator, 'sendBeacon', {
        configurable: true,
        value: (url, blob) => {
            if (beaconAccepts) {
                beaconed.push(blob);
            }

            return beaconAccepts;
        },
    });

    new Function(source)();
});

beforeEach(() => {
    beaconed.length = 0;
    beaconAccepts = true;
    fetch.mockClear();
    window.__falconAnalytics.context = 'scellé-1';
});

/** The bodies sent once the page is left, by beacon then by fetch. */
async function leave() {
    window.dispatchEvent(new Event('pagehide'));

    const byBeacon = await Promise.all(beaconed.map(async (blob) => JSON.parse(await blob.text())));
    const byFetch = fetch.mock.calls.map(([, request]) => JSON.parse(request.body));

    return [...byBeacon, ...byFetch];
}

function click(name) {
    document.body.innerHTML = `<button data-track-event="${name}">x</button>`;
    document.querySelector('button').click();
}

test('the beacon carries the context', async () => {
    click('cta.beacon');

    const [body] = await leave();

    expect(body.context).toBe('scellé-1');
    expect(body.events.map((event) => event.name)).toContain('cta.beacon');
});

test('the fallback through fetch carries it too', async () => {
    beaconAccepts = false;
    click('cta.fetch');

    const [body] = await leave();

    expect(fetch).toHaveBeenCalledTimes(1);
    expect(body.context).toBe('scellé-1');
});

test('every batch of a long flush carries it', async () => {
    document.body.innerHTML = '<button data-track-event="cta.many">x</button>';

    for (let i = 0; i < 150; i++) {
        document.querySelector('button').click();
    }

    const bodies = await leave();

    expect(bodies).toHaveLength(2);
    expect(bodies.map((body) => body.context)).toEqual(['scellé-1', 'scellé-1']);
});

test('it is read at each send, never kept from the start', async () => {
    // What a page swapped without a reload does: a new configuration, not an edited one.
    window.__falconAnalytics = { ...window.__falconAnalytics, context: 'scellé-2' };
    click('cta.swapped');

    const [body] = await leave();

    expect(body.context).toBe('scellé-2');
});

test('a page drawn for nobody sends none', async () => {
    delete window.__falconAnalytics.context;
    click('cta.anonymous');

    const [body] = await leave();

    expect(body).not.toHaveProperty('context');
});
