@php
    $routeName = config('analytics.marketing.route_name', 'marketing');
    $campaignUrl = route($routeName.'.campaigns.show', $ad->campaign_id);
@endphp

<div class="space-y-6">

    <div>
        <a href="{{ $campaignUrl }}" class="inline-flex cursor-pointer items-center gap-x-1 text-[12px] font-medium text-secondary transition-colors hover:text-primary">
            <x-ui.icon name="arrow-left" class="h-3.5 w-3.5" />
            {{ __('Retour à :campaign', ['campaign' => $ad->campaign->name]) }}
        </a>
    </div>

    {{-- Ad header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                <x-ui.icon name="rectangle-stack" class="h-3.5 w-3.5" /> {{ __('Pub') }}
            </div>
            <h1 class="text-2xl font-semibold tracking-tight text-primary">{{ $ad->name }}</h1>
            <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] text-secondary">
                <span>{{ __('Campagne') }}</span>
                <a href="{{ $campaignUrl }}" class="cursor-pointer font-medium text-primary hover:underline">{{ $ad->campaign->name }}</a>
                @if ($ad->campaign->platform)<x-ui.badge color="gray">{{ $ad->campaign->platform }}</x-ui.badge>@endif
            </div>
        </div>
        <x-ui.button variant="secondary" wire:click="editAd"><x-ui.icon name="pencil-square" class="h-4 w-4" /> {{ __('Modifier la pub') }}</x-ui.button>
    </div>

    {{-- Performance (deferred content) --}}
    <div>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <x-ui.section-header :title="__('Performance')" :description="__('du :from au :to', ['from' => $range->from->isoFormat('D MMM'), 'to' => $range->to->isoFormat('D MMM YYYY')])" />
            @include('analytics::livewire.dashboard.partials.filters')
        </div>
        <livewire:analytics-ad-detail-content :period="$period" :subject="$subject" :ref-id="$ad->id" :key="'ad-content-'.$ad->id.'-'.$period.'-'.$subject" />
    </div>

    {{-- Conditions --}}
    <x-ui.card>
        <x-ui.section-header :title="__('Conditions d\'URL')" :description="__('La pub correspond si tous ces paramètres sont présents dans l\'URL de la visite.')" class="mb-4" />
        <div class="flex flex-wrap items-center gap-1.5">
            @forelse ($ad->match_conditions ?? [] as $condition)
                <x-analytics::condition-chip :param="$condition['param']" :value="$condition['value']" />
            @empty
                <span class="inline-flex items-center gap-1 text-[11px] text-amber-600 dark:text-amber-400"><x-ui.icon name="exclamation-triangle" class="h-3.5 w-3.5" /> {{ __('Aucune condition : ne correspondra à aucun trafic') }}</span>
            @endforelse
        </div>
    </x-ui.card>

    {{-- Ad edit modal --}}
    <div x-on:keydown.escape.window="$wire.modal !== '' && $wire.closeModal()">
        @include('analytics::livewire.dashboard.partials.marketing-ad-form')
    </div>

</div>
