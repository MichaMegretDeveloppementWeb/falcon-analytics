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
@endphp
{{ __($labels[$key] ?? \Illuminate\Support\Str::headline((string) $value)) }}
