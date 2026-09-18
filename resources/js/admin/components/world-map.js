import { payloadFor } from './chart-parts.js';

const SVG = 'http://www.w3.org/2000/svg';

/** The width of the base map, in its own units. */
const MAP_WIDTH = 1000;

/**
 * A point on the Miller projection the base map is drawn with, latitudes
 * clamped to the map's visible band.
 */
export function project(latitude, longitude) {
    const clamped = Math.max(-60, Math.min(85, latitude));
    const x = (longitude + 180) * (MAP_WIDTH / 360);
    const miller = 1.25 * Math.log(Math.tan(Math.PI / 4 + 0.4 * clamped * Math.PI / 180));
    const y = (2.047423 - miller) * (MAP_WIDTH / (2 * Math.PI));

    return [x, y];
}

/** An SVG circle with the given attributes. */
function circle(attributes) {
    const node = document.createElementNS(SVG, 'circle');

    for (const [name, value] of Object.entries(attributes)) {
        node.setAttribute(name, String(value));
    }

    return node;
}

/**
 * The map of live connections. Markers are rebuilt in place on every tick,
 * sized in screen pixels so they look the same on a phone and a wide display.
 * Their colours come from classes, so a theme switch repaints them on its own.
 */
export function anWorldMap({ points, channel, onlineLabel, sessionLabel, sessionsLabel }) {
    return {
        points,
        mode: 'window',

        visible() {
            return this.mode === 'online' ? this.points.filter((point) => point.online > 0) : this.points;
        },

        labelFor(point, count) {
            const place = point.city || point.country || '?';
            const sessions = `${count} ${count > 1 ? sessionsLabel : sessionLabel}`;
            const online = point.online > 0 && this.mode !== 'online' ? ` · ${point.online} ${onlineLabel}` : '';

            return `${place} · ${sessions}${online}`;
        },

        markerFor(point, unitsPerPixel) {
            const [x, y] = project(point.latitude, point.longitude);
            const count = this.mode === 'online' ? point.online : point.total;
            const radius = Math.min(5 + Math.sqrt(count) * 1.4, 13) * unitsPerPixel;
            const online = point.online > 0;
            const group = document.createElementNS(SVG, 'g');

            group.setAttribute('data-an-tooltip', this.labelFor(point, count));

            if (online) {
                group.append(circle({ class: 'an-map-pulse an-map-online', cx: x, cy: y, r: radius * 1.5 }));
            }

            group.append(circle({ class: online ? 'an-map-online' : 'an-map-recent', cx: x, cy: y, r: radius, 'fill-opacity': 0.9 }));

            return group;
        },

        apply() {
            const unitsPerPixel = MAP_WIDTH / (this.$refs.svg.clientWidth || MAP_WIDTH);

            this.$refs.markers.replaceChildren(...this.visible().map((point) => this.markerFor(point, unitsPerPixel)));
        },

        refresh(detail) {
            const payload = payloadFor(detail, channel);

            if (! payload) {
                return;
            }

            this.points = payload.points;
            this.apply();
        },

        setMode(detail) {
            const payload = Array.isArray(detail) ? detail[0] : detail;

            if (! payload || ! payload.mode) {
                return;
            }

            this.mode = payload.mode;
            this.apply();
        },

        init() {
            this.apply();
        },
    };
}
