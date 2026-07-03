@props([
    'code' => null,
    'city' => null,
])

@php
    $countryCode = $code ? strtoupper((string) $code) : null;

    $name = $countryCode;
    if ($countryCode !== null && strlen($countryCode) === 2 && ctype_alpha($countryCode) && class_exists(\Locale::class)) {
        $resolved = \Locale::getDisplayRegion('-'.$countryCode, app()->getLocale());
        if (is_string($resolved) && $resolved !== '' && strtoupper($resolved) !== $countryCode) {
            $name = $resolved;
        }
    }
    $name = $name ?? __('Inconnu');
    $countryTitle = $name.($city ? ' ('.$city.')' : '');
@endphp

<span data-tooltip="{{ $countryTitle }}">{{ $name }}@if ($city) <span class="text-secondary">({{ $city }})</span>@endif</span>
