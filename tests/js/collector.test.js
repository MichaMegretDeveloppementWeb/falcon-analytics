import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { beforeAll, beforeEach, describe, expect, test, vi } from 'vitest';

/*
 * The score an element carries: a whole number the column can hold goes with
 * its event, anything else is left out and the event still counts. The server
 * refuses a batch holding any other score, so one bad attribute would
 * otherwise lose every event sent with it.
 */

const source = readFileSync(join(import.meta.dirname, '../../resources/js/collector.js'), 'utf8');

/** The collector, loaded once as a page loads it, with its endpoint set. */
beforeAll(() => {
    vi.useFakeTimers();

    window.__falconAnalytics = { endpoint: '/analytics/collect', route: 'home' };
    globalThis.fetch = vi.fn(() => Promise.resolve());

    // No beacon, so every batch is handed to fetch, where it can be read.
    Object.defineProperty(navigator, 'sendBeacon', { configurable: true, value: undefined });

    new Function(source)();
});

beforeEach(() => {
    document.body.innerHTML = '';
    fetch.mockClear();
});

/** The markup, put on the page, and its first element. */
function placed(markup) {
    document.body.innerHTML = markup;

    return document.body.firstElementChild;
}

/** The event of that name the collector sends once the page is left. */
function sent(name) {
    window.dispatchEvent(new Event('pagehide'));

    return fetch.mock.calls
        .flatMap(([, request]) => JSON.parse(request.body).events)
        .find((event) => event.name === name);
}

describe('on a click', () => {
    test('a whole score goes with the event', () => {
        placed('<button data-track-event="lead.created" data-track-value="3">Demander</button>').click();

        expect(sent('lead.created').value).toBe(3);
    });

    test('a penalty is a whole score too', () => {
        placed('<button data-track-event="lead.cancelled" data-track-value="-2">Annuler</button>').click();

        expect(sent('lead.cancelled').value).toBe(-2);
    });

    test('a score with a fraction is left out, and the click still counts', () => {
        placed('<button data-track-event="lead.created" data-track-value="3.5">Demander</button>').click();

        const event = sent('lead.created');

        expect(event).toBeDefined();
        expect(event).not.toHaveProperty('value');
    });

    test('a score beyond what the column holds is left out, and the click still counts', () => {
        placed('<button data-track-event="lead.created" data-track-value="2147483648">Demander</button>').click();

        const event = sent('lead.created');

        expect(event).toBeDefined();
        expect(event).not.toHaveProperty('value');
    });
});

describe('on a form sent', () => {
    test('a whole score goes with the event', () => {
        placed('<form data-track-event="quote.sent" data-track-value="5"></form>')
            .dispatchEvent(new Event('submit', { cancelable: true }));

        expect(sent('quote.sent').value).toBe(5);
    });

    test('a score with a fraction is left out, and the form still counts', () => {
        placed('<form data-track-event="quote.sent" data-track-value="2.5"></form>')
            .dispatchEvent(new Event('submit', { cancelable: true }));

        const event = sent('quote.sent');

        expect(event).toBeDefined();
        expect(event).not.toHaveProperty('value');
    });
});
