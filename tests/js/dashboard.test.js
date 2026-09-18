import { afterEach, describe, expect, test, vi } from 'vitest';
import { anObjectivePicker } from '../../resources/js/admin/dashboard/objective-picker.js';
import { anRealtimeTabs } from '../../resources/js/admin/dashboard/realtime-tabs.js';
import { anSessionTabs } from '../../resources/js/admin/dashboard/session-tabs.js';
import { anTooltipHost } from '../../resources/js/admin/dashboard/tooltip-host.js';

/*
 * The small components of the dashboard's screens: the tooltip, the session's
 * layout, the real-time tabs and the objective pickers.
 */

afterEach(() => {
    vi.restoreAllMocks();
    delete window.matchMedia;
    document.body.replaceChildren();
});

describe('the tooltip', () => {
    /** The host as Alpine would hold it, running `$nextTick` at once. */
    function host() {
        const tooltip = anTooltipHost();

        tooltip.$refs = { tip: document.createElement('div') };
        tooltip.$nextTick = (callback) => callback();

        return tooltip;
    }

    test('shows the value of the element under the pointer', () => {
        const tooltip = host();
        const target = document.createElement('span');

        target.setAttribute('data-an-tooltip', 'Paris · 4 sessions');
        document.body.append(target);
        tooltip.move({ target });

        expect(tooltip.show).toBe(true);
        expect(tooltip.text).toBe('Paris · 4 sessions');
    });

    test('also from a child of that element', () => {
        const tooltip = host();
        const target = document.createElement('span');
        const child = document.createElement('b');

        target.setAttribute('data-an-tooltip', 'Lyon');
        target.append(child);
        document.body.append(target);
        tooltip.move({ target: child });

        expect(tooltip.text).toBe('Lyon');
    });

    test('hides over anything else', () => {
        const tooltip = host();

        tooltip.show = true;
        tooltip.move({ target: document.body });

        expect(tooltip.show).toBe(false);
    });

    test('stays inside the screen', () => {
        const tooltip = host();
        const target = document.createElement('span');

        target.getBoundingClientRect = () => ({ left: -40, top: 2, bottom: 20 });
        tooltip.place(target);

        expect(tooltip.x).toBe(8);
        expect(tooltip.y).toBe(28);
    });
});

describe('the session layout', () => {
    /** A media query that can be told the screen changed. */
    function screen(wide) {
        const listeners = new Set();
        const query = {
            matches: wide,
            addEventListener: (_type, listener) => listeners.add(listener),
            removeEventListener: (_type, listener) => listeners.delete(listener),
        };

        window.matchMedia = vi.fn(() => query);

        return { listeners, resize: (matches) => listeners.forEach((listener) => listener({ matches })) };
    }

    test('starts from the width of the screen', () => {
        screen(true);

        expect(anSessionTabs().desktop).toBe(true);
    });

    test('follows the screen while it is on the page', () => {
        const { resize } = screen(false);
        const layout = anSessionTabs();

        layout.init();
        resize(true);

        expect(layout.desktop).toBe(true);
    });

    test('lets go of the screen once removed', () => {
        const { listeners } = screen(false);
        const layout = anSessionTabs();

        layout.init();
        layout.destroy();

        expect(listeners.size).toBe(0);
    });
});

describe('the real-time tabs', () => {
    test('choosing a view tells the map', () => {
        const tabs = anRealtimeTabs();

        tabs.$dispatch = vi.fn();
        tabs.choose('online');

        expect(tabs.tab).toBe('online');
        expect(tabs.$dispatch).toHaveBeenCalledWith('an-realtime-mode', { mode: 'online' });
    });
});

describe('the objective picker', () => {
    test('opens empty, and closes', () => {
        const picker = anObjectivePicker();

        picker.search = 'old';
        picker.toggle();

        expect(picker.open).toBe(true);
        expect(picker.search).toBe('');

        picker.toggle();

        expect(picker.open).toBe(false);
    });
});
