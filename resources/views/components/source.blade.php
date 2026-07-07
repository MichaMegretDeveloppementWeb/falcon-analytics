@props([
    'value' => null,
])

@php
    $labels = [
        'direct' => 'Direct',
        'organic' => 'Recherche naturelle',
        'social' => 'Social naturel',
        'paid' => 'Payant',
        'referral' => 'Référent',
        'email' => 'E-mail',
        'campaign' => 'Référent',
    ];
    $key = strtolower((string) $value);
    $sourceLabel = __($labels[$key] ?? \Illuminate\Support\Str::headline((string) $value));
@endphp
<span data-tooltip="{{ $sourceLabel }}">{{ $sourceLabel }}</span>
