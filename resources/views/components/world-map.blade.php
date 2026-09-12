@props([
    'points' => [],
    'event' => 'analytics-realtime-tick',
    'channel' => 'map',
])

{{--
    A map of the live connections, entirely self-contained: the base map is an
    embedded SVG — no tile server and no external library — with per-country
    borders drawn at a constant stroke (vector-effect), a Miller projection, and
    a fixed world view.

    The markers live under wire:ignore and are regenerated IN PLACE on every
    tick (the `map` payload), sized in SCREEN PIXELS so they look the same on a
    phone and on a wide display. The page's 30 min / Online tab filters them
    through the `analytics-realtime-mode` event, and the tooltip goes through
    the dashboard's delegated `data-tooltip`.

    Its four colours are tokens · land, borders, the live green and the recent
    accent. They were literals, and the dark theme was a second set of classes
    beside them; a token carries both, so those classes are gone.
--}}
<div
    wire:ignore
    class="an:relative"
    x-data="{
        points: @js(array_values($points)),
        mode: 'window',
        onlineLabel: @js(__('en ligne')),
        sessionLabel: @js(__('session')),
        sessionsLabel: @js(__('sessions')),
        visible() {
            return this.mode === 'online' ? this.points.filter(p => p.online > 0) : this.points;
        },
        {{-- The Miller projection, identical to the generated base map
             (yTop = miller(85 deg)). --}}
        online() { return window.falconToken('--an-online'); },
        recent() { return window.falconToken('--an-accent'); },
        project(lat, lon) {
            const clamped = Math.max(-60, Math.min(85, lat));
            const x = (lon + 180) * (1000 / 360);
            const yM = 1.25 * Math.log(Math.tan(Math.PI / 4 + 0.4 * clamped * Math.PI / 180));
            const y = (2.047423 - yM) * (1000 / (2 * Math.PI));
            return [x, y];
        },
        esc(value) { return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/\x22/g, '&quot;'); },
        apply() {
            {{-- Radius computed in screen pixels, so markers keep the same size
                 on a phone and on a wide display. --}}
            const unitsPerPx = 1000 / (this.$refs.svg.clientWidth || 1000);
            let html = '';
            for (const p of this.visible()) {
                const [x, y] = this.project(p.latitude, p.longitude);
                const count = this.mode === 'online' ? p.online : p.total;
                const r = Math.min(5 + Math.sqrt(count) * 1.4, 13) * unitsPerPx;
                const online = p.online > 0;
                const place = p.city || p.country || '?';
                const suffix = count + ' ' + (count > 1 ? this.sessionsLabel : this.sessionLabel);
                const label = this.esc(place + ' · ' + suffix + (online && this.mode !== 'online' ? ' · ' + p.online + ' ' + this.onlineLabel : ''));
                html += '<g data-tooltip=\x22' + label + '\x22>';
                if (online) {
                    html += '<circle class=\x22fa-map-pulse\x22 cx=\x22' + x + '\x22 cy=\x22' + y + '\x22 r=\x22' + (r * 1.5) + '\x22 fill=\x22' + this.online() + '\x22></circle>';
                }
                html += '<circle cx=\x22' + x + '\x22 cy=\x22' + y + '\x22 r=\x22' + r + '\x22 fill=\x22' + (online ? this.online() : this.recent()) + '\x22 fill-opacity=\x220.9\x22></circle>';
                html += '</g>';
            }
            this.$refs.markers.innerHTML = html;
        },
        refresh(detail) {
            const payload = (Array.isArray(detail) ? detail[0] : detail)?.[@js($channel)];
            if (! payload) { return; }
            this.points = payload.points;
            this.apply();
        },
        setMode(detail) {
            const payload = Array.isArray(detail) ? detail[0] : detail;
            if (! payload || ! payload.mode) { return; }
            this.mode = payload.mode;
            this.apply();
        },
        init() { this.apply(); },
    }"
    x-on:{{ $event }}.window="refresh($event.detail)"
    x-on:analytics-realtime-mode.window="setMode($event.detail)"
    x-on:resize.window.debounce.250ms="apply()"
>
    <svg x-ref="svg" viewBox="0 0 1000 516" preserveAspectRatio="xMidYMid meet" class="an:w-full" role="img" aria-label="{{ __('Carte des connexions') }}">
        <g class="an:fill-map-land an:stroke-map-border" stroke-width="1">
            @include('analytics::livewire.dashboard.partials.world-map-path')
        </g>
        <g x-ref="markers"></g>
    </svg>

    <style>
        @keyframes fa-map-pulse {
            0% { opacity: .5; transform: scale(1); }
            70% { opacity: 0; transform: scale(2.4); }
            100% { opacity: 0; transform: scale(2.4); }
        }
        .fa-map-pulse {
            animation: fa-map-pulse 2s cubic-bezier(0, 0, .2, 1) infinite;
            transform-box: fill-box;
            transform-origin: center;
        }
    </style>
</div>
