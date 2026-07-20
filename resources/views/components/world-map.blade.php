@props([
    'points' => [],
    'event' => 'analytics-realtime-tick',
    'channel' => 'map',
])

{{--
    Carte des connexions, entierement autonome : fond de carte SVG embarque
    (aucune tuile ni bibliotheque externe), frontieres par pays au trait
    constant (vector-effect), projection Miller, vue monde fixe comme la
    reference. Les marqueurs vivent sous wire:ignore et se regenerent EN PLACE
    a chaque tick (payload `map`), dimensionnes en PIXELS ECRAN (memes tailles
    sur mobile et grand ecran), et l'onglet 30 min / En ligne de la page filtre
    les points via l'evenement `analytics-realtime-mode`. Couleurs calquees sur
    la reference Wix : pays #F5F5F5, frontieres #DDDDDD, en ligne #54CE91,
    recentes #116DFF. Tooltip via le systeme delegue data-tooltip du dashboard.
--}}
<div
    wire:ignore
    class="relative"
    x-data="{
        points: @js(array_values($points)),
        mode: 'window',
        onlineLabel: @js(__('en ligne')),
        sessionLabel: @js(__('session')),
        sessionsLabel: @js(__('sessions')),
        visible() {
            return this.mode === 'online' ? this.points.filter(p => p.online > 0) : this.points;
        },
        {{-- Projection Miller, identique au fond genere (yTop = miller(85 deg)). --}}
        project(lat, lon) {
            const clamped = Math.max(-60, Math.min(85, lat));
            const x = (lon + 180) * (1000 / 360);
            const yM = 1.25 * Math.log(Math.tan(Math.PI / 4 + 0.4 * clamped * Math.PI / 180));
            const y = (2.047423 - yM) * (1000 / (2 * Math.PI));
            return [x, y];
        },
        esc(value) { return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/\x22/g, '&quot;'); },
        apply() {
            {{-- Rayon calcule en pixels ECRAN (unites/px du rendu reel) : les
                 marqueurs gardent la meme taille sur mobile comme sur grand
                 ecran. --}}
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
                    html += '<circle class=\x22fa-map-pulse\x22 cx=\x22' + x + '\x22 cy=\x22' + y + '\x22 r=\x22' + (r * 1.5) + '\x22 fill=\x22#54CE91\x22></circle>';
                }
                html += '<circle cx=\x22' + x + '\x22 cy=\x22' + y + '\x22 r=\x22' + r + '\x22 fill=\x22' + (online ? '#54CE91' : '#116DFF') + '\x22 fill-opacity=\x220.9\x22></circle>';
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
    <svg x-ref="svg" viewBox="0 0 1000 516" preserveAspectRatio="xMidYMid meet" class="w-full" role="img" aria-label="{{ __('Carte des connexions') }}">
        <g class="fill-[#F5F5F5] stroke-[#DDDDDD] dark:fill-gray-800 dark:stroke-gray-700" stroke-width="1">
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
