@props([
    'code' => null,
    'city' => null,
])

@php
    $name = \Falcon\Analytics\Support\CountryLabel::for($code === null ? null : (string) $code) ?? __('Inconnu');
    $countryTitle = $name.($city ? ' ('.$city.')' : '');
@endphp

<span data-an-tooltip="{{ $countryTitle }}">{{ $name }}@if ($city) <span class="an:text-secondary">({{ $city }})</span>@endif</span>
