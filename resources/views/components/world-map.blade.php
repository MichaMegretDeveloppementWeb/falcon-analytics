@props([
    'points' => [],
    'event' => 'an-realtime-tick',
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
    through the `an-realtime-mode` event, and the tooltip goes through
    the dashboard's delegated `data-an-tooltip`.

    Its four colours are tokens · land, borders, the live green and the recent
    accent. They were literals, and the dark theme was a second set of classes
    beside them; a token carries both, so those classes are gone.
--}}
<div
    wire:ignore
    class="an:relative"
    x-data="anWorldMap({
        points: @js(array_values($points)),
        channel: @js($channel),
        onlineLabel: @js(__('en ligne')),
        sessionLabel: @js(__('session')),
        sessionsLabel: @js(__('sessions')),
    })"
    x-on:{{ $event }}.window="refresh($event.detail)"
    x-on:an-realtime-mode.window="setMode($event.detail)"
    x-on:resize.window.debounce.250ms="apply()"
>
    <svg x-ref="svg" viewBox="0 0 1000 516" preserveAspectRatio="xMidYMid meet" class="an:w-full" role="img" aria-label="{{ __('Carte des connexions') }}">
        <g class="an:fill-map-land an:stroke-map-border" stroke-width="1">
            @include('analytics::livewire.dashboard.partials.world-map-path')
        </g>
        <g x-ref="markers"></g>
    </svg>

    <style>
        /* The two marker colours, held by the stylesheet and not by the script ·
           an SVG circle is reached by CSS, so a theme switch repaints it with
           the rest of the page and nothing has to be redrawn. */
        .an-map-online { fill: var(--an-online); }
        .an-map-recent { fill: var(--an-accent); }

        @keyframes an-map-pulse {
            0% { opacity: .5; transform: scale(1); }
            70% { opacity: 0; transform: scale(2.4); }
            100% { opacity: 0; transform: scale(2.4); }
        }
        .an-map-pulse {
            animation: an-map-pulse 2s cubic-bezier(0, 0, .2, 1) infinite;
            transform-box: fill-box;
            transform-origin: center;
        }
    </style>
</div>
