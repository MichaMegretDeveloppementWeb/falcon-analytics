@props([
    'value' => null,
])

@php
    $labels = [
        'direct' => 'Direct',
        'organic' => 'Naturel',
        'social' => 'Réseaux sociaux',
        'paid' => 'Payant',
        'referral' => 'Référent',
        'email' => 'E-mail',
        'campaign' => 'Campagne',
    ];
    $key = strtolower((string) $value);
    $sourceLabel = __($labels[$key] ?? \Illuminate\Support\Str::headline((string) $value));
@endphp
<span title="{{ $sourceLabel }}">{{ $sourceLabel }}</span>
