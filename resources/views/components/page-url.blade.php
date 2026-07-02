@props([
    'route' => null,
])

@php
    // The clean URL for a route name (its URI pattern, no query params). Falls
    // back to the raw route name when the route no longer exists in the host.
    $uri = null;
    if ($route) {
        $uri = \Illuminate\Support\Facades\Route::getRoutes()->getByName($route)?->uri();
    }

    $display = $uri !== null ? '/'.ltrim($uri, '/') : ($route ?? __('Inconnu'));
@endphp

{{ $display }}
