@props([
    'route' => null,
    'url' => null,
])

@php
    // The real page path (dynamic value, no domain or query) when the stored URL
    // is available, else the route's URI pattern, else the raw route name.
    $display = \Falcon\Analytics\Support\PageUrl::resolve($route, $url);
    $display = $display !== '' ? $display : __('Inconnu');
@endphp

{{-- data-an-tooltip reveals the full path on hover via the dashboard tooltip host. --}}
<span data-an-tooltip="{{ $display }}">{{ $display }}</span>
