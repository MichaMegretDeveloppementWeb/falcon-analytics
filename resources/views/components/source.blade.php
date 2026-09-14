@props([
    'value' => null,
])

@php
    $sourceLabel = \Falcon\Analytics\Support\SourceLabel::for($value !== null ? (string) $value : null);
@endphp
<span data-an-tooltip="{{ $sourceLabel }}">{{ $sourceLabel }}</span>
