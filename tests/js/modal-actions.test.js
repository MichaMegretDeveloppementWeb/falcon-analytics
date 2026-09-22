import { afterAll, afterEach, describe, expect, test, vi } from 'vitest';
import { anCloseWhenDone, anOpenWhenDone } from '../../resources/js/admin/modal-actions.js';

/*
 * A modal of the kit opens and closes on window events. These two wait for
 * the server's answer first, and act only on a yes: a form that could not be
 * read never opens, and one refused on validation stays open.
 */

const heard = [];

function listen(event) {
    const record = (e) => heard.push([event, e.detail]);

    window.addEventListener(event, record);

    return () => window.removeEventListener(event, record);
}

const stops = [listen('ui-open-modal'), listen('ui-close-modal')];

afterEach(() => {
    heard.length = 0;
});

afterAll(() => stops.forEach((stop) => stop()));

describe('opening', () => {
    test('opens the modal once the server says yes', async () => {
        const answer = await anOpenWhenDone(document.body)(Promise.resolve(true), 'an-campaign-form');

        expect(answer).toBe(true);
        expect(heard).toEqual([['ui-open-modal', 'an-campaign-form']]);
    });

    test('opens nothing on a no', async () => {
        await anOpenWhenDone(document.body)(Promise.resolve(false), 'an-campaign-form');

        expect(heard).toEqual([]);
    });

    test('waits for the answer before opening', async () => {
        let answer;
        const pending = anOpenWhenDone(document.body)(new Promise((resolve) => { answer = resolve; }), 'an-campaign-form');

        await vi.waitFor(() => expect(answer).toBeTypeOf('function'));
        expect(heard).toEqual([]);

        answer(true);
        await pending;

        expect(heard).toEqual([['ui-open-modal', 'an-campaign-form']]);
    });
});

describe('closing', () => {
    test('closes the modal once the server says it is done', async () => {
        await anCloseWhenDone(document.body)(Promise.resolve(true), 'an-campaign-form');

        expect(heard).toEqual([['ui-close-modal', 'an-campaign-form']]);
    });

    test('keeps it open on a no · a refused form stays in front of whoever fills it', async () => {
        await anCloseWhenDone(document.body)(Promise.resolve(false), 'an-campaign-form');

        expect(heard).toEqual([]);
    });
});
