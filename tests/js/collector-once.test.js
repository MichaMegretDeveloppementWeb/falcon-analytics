import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { beforeAll, expect, test, vi } from 'vitest';

/*
 * A page that loads the collector twice still counts each thing once: the
 * second start does nothing.
 */

const source = readFileSync(join(import.meta.dirname, '../../resources/js/collector.js'), 'utf8');

beforeAll(() => {
    vi.useFakeTimers();

    window.__falconAnalytics = { endpoint: '/analytics/collect', route: 'home' };
    globalThis.fetch = vi.fn(() => Promise.resolve());
    Object.defineProperty(navigator, 'sendBeacon', { configurable: true, value: undefined });

    new Function(source)();
    new Function(source)();
});

/** Every event sent once the page is left. */
function leave() {
    window.dispatchEvent(new Event('pagehide'));

    return fetch.mock.calls.flatMap(([, request]) => JSON.parse(request.body).events);
}

test('loaded twice, the collector sends one page view and one click', () => {
    document.body.innerHTML = '<button data-track-event="cta.book">Réserver</button>';
    document.querySelector('button').click();

    const sent = leave();

    expect(sent.filter((event) => event.type === 'pageview')).toHaveLength(1);
    expect(sent.filter((event) => event.name === 'cta.book')).toHaveLength(1);
});
