import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';
import { anCopyButton } from '../../resources/js/admin/components/copy-button.js';

/*
 * The copy button: a check mark for a copy that happened, none for one the
 * browser refused, and a way through when the Clipboard API is not offered.
 */

beforeEach(() => {
    vi.useFakeTimers();
});

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
    delete navigator.clipboard;
    delete document.execCommand;
});

/** A Clipboard API that accepts, or refuses, what it is asked to copy. */
function clipboard(accepts) {
    const written = [];

    Object.defineProperty(navigator, 'clipboard', {
        configurable: true,
        value: {
            writeText: (text) => {
                if (! accepts) {
                    return Promise.reject(new Error('refused'));
                }

                written.push(text);

                return Promise.resolve();
            },
        },
    });

    return written;
}

describe('with the Clipboard API', () => {
    test('copies, confirms, and lets the confirmation go', async () => {
        const written = clipboard(true);
        const button = anCopyButton('visitor-42');

        await button.copy();

        expect(written).toEqual(['visitor-42']);
        expect(button.copied).toBe(true);

        vi.advanceTimersByTime(1400);

        expect(button.copied).toBe(false);
    });

    test('falls back to a field when the page refuses it', async () => {
        clipboard(false);
        document.execCommand = vi.fn(() => true);
        const button = anCopyButton('visitor-42');

        await button.copy();

        expect(document.execCommand).toHaveBeenCalledWith('copy');
        expect(button.copied).toBe(true);
    });
});

describe('without it', () => {
    test('copies through a field it removes afterwards', async () => {
        document.execCommand = vi.fn(() => true);
        const button = anCopyButton('visitor-42');

        await button.copy();

        expect(button.copied).toBe(true);
        expect(document.querySelector('textarea')).toBeNull();
    });

    test('confirms nothing when the browser does not copy', async () => {
        document.execCommand = vi.fn(() => false);
        const button = anCopyButton('visitor-42');

        await button.copy();

        expect(button.copied).toBe(false);
    });
});

test('a button removed before its confirmation ends leaves no timer behind', async () => {
    clipboard(true);
    const button = anCopyButton('visitor-42');

    await button.copy();
    button.destroy();

    expect(vi.getTimerCount()).toBe(0);
});
