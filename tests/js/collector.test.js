import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { beforeAll, beforeEach, describe, expect, test, vi } from 'vitest';

/*
 * The collector as a page runs it: what it sends, when, and what it leaves
 * out. It runs on every page of every visitor, so what it does is held here
 * before its code is touched.
 */

const source = readFileSync(join(import.meta.dirname, '../../resources/js/collector.js'), 'utf8');

/** What the collector sent before any test ran: the page view of the load. */
let atLoad = [];

/** The collector, loaded once as a page loads it, with its endpoint set. */
beforeAll(() => {
    vi.useFakeTimers();

    window.__falconAnalytics = { endpoint: '/analytics/collect', route: 'home' };
    globalThis.fetch = vi.fn(() => Promise.resolve());

    // No beacon, so every batch is handed to fetch, where it can be read.
    Object.defineProperty(navigator, 'sendBeacon', { configurable: true, value: undefined });

    new Function(source)();

    atLoad = leave();
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

/** Every batch handed to fetch so far, each as its list of events. */
function batches() {
    return fetch.mock.calls.map(([, request]) => JSON.parse(request.body).events);
}

/** Everything the collector sends once the page is left. */
function leave() {
    window.dispatchEvent(new Event('pagehide'));

    return batches().flat();
}

/** The event of that name the collector sends once the page is left. */
function sent(name) {
    return leave().find((event) => event.name === name);
}

describe('at load', () => {
    test('a page view goes, with the route and the address', () => {
        expect(atLoad).toHaveLength(1);
        expect(atLoad[0]).toMatchObject({ type: 'pageview', route: 'home', url: location.href });
    });
});

describe('what counts as a click', () => {
    test('a click on plain text is not recorded', () => {
        placed('<p>Un paragraphe</p>').click();

        expect(leave().filter((event) => event.type === 'click')).toEqual([]);
    });

    test('a click inside an ignored zone is not recorded', () => {
        placed('<div data-track-ignore><button data-track-event="cta.hidden">Masqué</button></div>')
            .querySelector('button')
            .click();

        expect(sent('cta.hidden')).toBeUndefined();
    });

    test('the properties gather up the tree, the nearest wins, and the zone applies below it', () => {
        placed('<section data-track-section="hero" data-track-prop-listing-id="42"><div data-track-prop-listing-id="7" data-track-prop-plan="pro"><button data-track-event="cta.book">Réserver</button></div></section>')
            .querySelector('button')
            .click();

        expect(sent('cta.book').props).toEqual({ listing_id: '7', plan: 'pro', section: 'hero' });
    });
});

describe('the text of a click', () => {
    test('a label as long as the server keeps arrives whole', () => {
        const label = 'a'.repeat(200);

        placed(`<button data-track-event="cta.long" data-track-label="${label}">x</button>`).click();

        expect(sent('cta.long').text).toBe(label);
    });

    test('a longer label is cut where the server would refuse it', () => {
        placed(`<button data-track-event="cta.longer" data-track-label="${'b'.repeat(300)}">x</button>`).click();

        expect(sent('cta.longer').text).toHaveLength(255);
    });

    test("without a label, the element's own text goes", () => {
        placed('<button data-track-event="cta.text">  Demander un devis  </button>').click();

        expect(sent('cta.text').text).toBe('Demander un devis');
    });
});

describe('sending', () => {
    test('a hundred and fifty events leave in two batches the server accepts', () => {
        const button = placed('<button data-track-event="cta.many">x</button>');

        for (let i = 0; i < 150; i++) {
            button.click();
        }

        leave();

        expect(batches().map((batch) => batch.length)).toEqual([100, 50]);
    });
});

/*
 * The score an element carries: a whole number the column can hold goes with
 * its event, anything else is left out and the event still counts. The server
 * refuses a batch holding any other score, so one bad attribute would
 * otherwise lose every event sent with it.
 */
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
