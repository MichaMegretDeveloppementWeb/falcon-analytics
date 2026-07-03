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

{{-- title carries the full path so a truncated cell still reveals it on hover
     (native tooltip: always on-screen, safe inside re-rendering Livewire). --}}
<span title="{{ $display }}">{{ $display }}</span>
