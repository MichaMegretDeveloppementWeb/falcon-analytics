@props([
    'route' => null,
    'url' => null,
])

@php
    // The real page path (dynamic value, no domain or query) when the stored URL
    // is available, else the route's URI pattern, else the raw route name.
    $display = \Falcon\Analytics\Support\PageUrl::resolve($route, $url);
@endphp

{{ $display !== '' ? $display : __('Inconnu') }}
