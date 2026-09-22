import { afterEach, beforeEach, expect, test, vi } from 'vitest';
import { anCopyList } from '../../resources/js/admin/components/copy-list.js';

/*
 * One copy behaviour for a whole list: each row hands it its value, and only
 * the row that was clicked shows the check mark.
 */

beforeEach(() => {
    vi.useFakeTimers();
    Object.defineProperty(navigator, 'clipboard', {
        configurable: true,
        value: { writeText: () => Promise.resolve() },
    });
});

afterEach(() => {
    vi.useRealTimers();
    delete navigator.clipboard;
    delete document.execCommand;
});

test('the row that was copied is the one that says so, and for a moment only', async () => {
    const list = anCopyList();

    await list.copy('visitor-42');

    expect(list.copied).toBe('visitor-42');

    vi.advanceTimersByTime(1400);

    expect(list.copied).toBeNull();
});

test('a second row copied takes the check mark from the first', async () => {
    const list = anCopyList();

    await list.copy('visitor-42');
    await list.copy('visitor-43');

    expect(list.copied).toBe('visitor-43');
});

test('a copy the browser refuses shows nothing', async () => {
    delete navigator.clipboard;
    document.execCommand = vi.fn(() => false);
    const list = anCopyList();

    await list.copy('visitor-42');

    expect(list.copied).toBeNull();
});

test('a list removed before its confirmation ends leaves no timer behind', async () => {
    const list = anCopyList();

    await list.copy('visitor-42');
    list.destroy();

    expect(vi.getTimerCount()).toBe(0);
});
