@props([
    'retention' => null, // days the figure's rows are kept · the sessions' by default
])

@php
    use Falcon\Analytics\Support\RetentionLabel;
    use Falcon\Analytics\Support\RetentionSettings;
@endphp

<span {{ $attributes->merge(['class' => 'an:text-muted']) }} data-an-tooltip="{{ RetentionLabel::unavailable($retention ?? RetentionSettings::sessions()) }}">{{ __('Indisponible') }}</span>
