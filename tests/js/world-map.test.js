import { describe, expect, test } from 'vitest';
import { anWorldMap, project } from '../../resources/js/admin/components/world-map.js';

/*
 * The map of live connections: which markers it draws, how they are labelled,
 * and what a tick or a change of view does to them.
 */

const PARIS = { city: 'Paris', country: 'France', latitude: 48.85, longitude: 2.35, total: 4, online: 2 };
const LYON = { city: 'Lyon', country: 'France', latitude: 45.76, longitude: 4.83, total: 1, online: 0 };

/** The map as Alpine would hold it, over a real SVG. */
function mounted(points) {
    const map = anWorldMap({ points, channel: 'map', onlineLabel: 'en ligne', sessionLabel: 'session', sessionsLabel: 'sessions' });
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    const markers = document.createElementNS('http://www.w3.org/2000/svg', 'g');

    svg.append(markers);
    map.$refs = { svg, markers };
    map.init();

    return map;
}

describe('the projection', () => {
    test('places longitude zero in the middle of the map', () => {
        expect(project(0, 0)[0]).toBe(500);
    });

    test('clamps the poles to the visible band', () => {
        expect(project(90, 0)).toEqual(project(85, 0));
        expect(project(-90, 0)).toEqual(project(-60, 0));
    });
});

describe('the markers', () => {
    test('one group per point, with a pulse for a place online', () => {
        const map = mounted([PARIS, LYON]);
        const groups = map.$refs.markers.querySelectorAll('g');

        expect(groups).toHaveLength(2);
        expect(groups[0].querySelectorAll('circle')).toHaveLength(2);
        expect(groups[0].querySelector('circle').getAttribute('class')).toBe('an-map-pulse an-map-online');
        expect(groups[1].querySelectorAll('circle')).toHaveLength(1);
        expect(groups[1].querySelector('circle').getAttribute('class')).toBe('an-map-recent');
    });

    test('label a place with its sessions, and who is online', () => {
        const map = mounted([PARIS, LYON]);
        const [paris, lyon] = map.$refs.markers.querySelectorAll('g');

        expect(paris.getAttribute('data-an-tooltip')).toBe('Paris · 4 sessions · 2 en ligne');
        expect(lyon.getAttribute('data-an-tooltip')).toBe('Lyon · 1 session');
    });

    test('carry a place name as text, never as markup', () => {
        const map = mounted([{ ...LYON, city: '<b>"Lyon"</b>' }]);

        expect(map.$refs.markers.querySelector('b')).toBeNull();
        expect(map.$refs.markers.querySelector('g').getAttribute('data-an-tooltip')).toBe('<b>"Lyon"</b> · 1 session');
    });
});

describe('what the page tells it', () => {
    test('a tick replaces the points', () => {
        const map = mounted([PARIS]);

        map.refresh({ map: { points: [PARIS, LYON] } });

        expect(map.$refs.markers.querySelectorAll('g')).toHaveLength(2);
    });

    test('a tick for another chart changes nothing', () => {
        const map = mounted([PARIS]);

        map.refresh({ pulse: { points: [] } });

        expect(map.$refs.markers.querySelectorAll('g')).toHaveLength(1);
    });

    test('the online view keeps the places online, and counts them', () => {
        const map = mounted([PARIS, LYON]);

        map.setMode([{ mode: 'online' }]);

        const groups = map.$refs.markers.querySelectorAll('g');

        expect(groups).toHaveLength(1);
        expect(groups[0].getAttribute('data-an-tooltip')).toBe('Paris · 2 sessions');
    });

    test('a change of view without a mode changes nothing', () => {
        const map = mounted([PARIS, LYON]);

        map.setMode({});

        expect(map.mode).toBe('window');
    });
});
