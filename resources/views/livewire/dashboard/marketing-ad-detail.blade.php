@php
    $campaignUrl = route('analytics.admin.marketing.campaigns.show', $ad->campaign_id);
@endphp

<x-analytics::root area="admin" class="an:space-y-6">

    <div>
        <a href="{{ $campaignUrl }}" class="an:inline-flex an:cursor-pointer an:items-center an:gap-x-1 an:text-[12px] an:font-medium an:text-secondary an:transition-colors an:hover:text-primary">
            <x-ui::icon name="arrow-left" class="an:h-3.5 an:w-3.5" />
            {{ __('Retour à :campaign', ['campaign' => $ad->campaign->name]) }}
        </a>
    </div>

    {{-- Ad header --}}
    <div class="an:flex an:flex-wrap an:items-start an:justify-between an:gap-4">
        <div class="an:min-w-0">
            <div class="an:mb-2 an:inline-flex an:items-center an:gap-1.5 an:rounded-full an:bg-emerald-50 an:px-2.5 an:py-1 an:text-[11px] an:font-semibold an:uppercase an:tracking-wide an:text-emerald-700 an:dark:bg-emerald-500/10 an:dark:text-emerald-400">
                <x-ui::icon name="rectangle-stack" class="an:h-3.5 an:w-3.5" /> {{ __('Pub') }}
            </div>
            <h1 class="an:text-2xl an:font-semibold an:tracking-tight an:text-primary">{{ $ad->name }}</h1>
            <div class="an:mt-1.5 an:flex an:flex-wrap an:items-center an:gap-x-2 an:gap-y-1 an:text-[13px] an:text-secondary">
                <span>{{ __('Campagne') }}</span>
                <a href="{{ $campaignUrl }}" class="an:cursor-pointer an:font-medium an:text-primary an:hover:underline">{{ $ad->campaign->name }}</a>
                @if ($ad->campaign->platform)<x-ui::badge color="gray">{{ $ad->campaign->platform }}</x-ui::badge>@endif
            </div>
        </div>
        <x-ui::button variant="secondary" x-on:click="$anOpenWhenDone($wire.$refs.adForm.$wire.editAd({{ $ad->id }}), 'an-ad-form')"><x-ui::icon name="pencil-square" class="an:h-4 an:w-4" /> {{ __('Modifier la pub') }}</x-ui::button>
    </div>

    {{-- Performance (deferred content) --}}
    <div>
        <div class="an:mb-4 an:flex an:flex-wrap an:items-center an:justify-between an:gap-3">
            <x-ui::section-header :title="__('Performance')" :description="__('du :from au :to', ['from' => $range->from->isoFormat('D MMM'), 'to' => $range->to->isoFormat('D MMM YYYY')])" />
            @include('analytics::livewire.dashboard.partials.filters')
        </div>
        <livewire:analytics::admin.widgets.ad-detail-content :period="$period" :subject="$subject" :ref-id="$ad->id" :key="'ad-content-'.$ad->id.'-'.$period.'-'.$subject" />
    </div>

    {{-- Conditions --}}
    <x-ui::card>
        <x-ui::section-header :title="__('Conditions d\'URL')" :description="__('La pub correspond si tous ces paramètres sont présents dans l\'URL de la visite.')" class="an:mb-4" />
        <div class="an:flex an:flex-wrap an:items-center an:gap-1.5">
            @forelse ($ad->match_conditions ?? [] as $condition)
                <x-analytics::condition-chip :param="$condition['param']" :value="$condition['value']" />
            @empty
                <span class="an:inline-flex an:items-center an:gap-1 an:text-[11px] an:text-amber-600 an:dark:text-amber-400"><x-ui::icon name="exclamation-triangle" class="an:h-3.5 an:w-3.5" /> {{ __('Aucune condition : ne correspondra à aucun trafic') }}</span>
            @endforelse
        </div>
    </x-ui::card>

    {{-- Ad form --}}
    <livewire:analytics::admin.ad-form :campaign-id="$ad->campaign_id" wire:ref="adForm" wire:key="ad-form" />

</x-analytics::root>
