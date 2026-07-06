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

    {{-- Performance --}}
    <div>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <x-ui.section-header :title="__('Performance')" :description="__('du :from au :to', ['from' => $range->from->isoFormat('D MMM'), 'to' => $range->to->isoFormat('D MMM YYYY')])" />
            @include('analytics::livewire.dashboard.partials.filters')
        </div>
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-analytics::kpi-card :label="__('Sessions')" :value="number_format($sessions, 0, ',', ' ')" icon="cursor-arrow-rays" :metric="$sessionsDelta">
                <div wire:key="a-spark-s-{{ $range->days }}-{{ $subject }}" class="mt-3"><x-analytics::sparkline :values="$trendData" /></div>
            </x-analytics::kpi-card>
            <x-analytics::kpi-card :label="__('Visiteurs')" :value="number_format($visitors, 0, ',', ' ')" icon="users" :metric="$visitorsDelta">
                <div wire:key="a-spark-v-{{ $range->days }}-{{ $subject }}" class="mt-3"><x-analytics::sparkline :values="$trendData" /></div>
            </x-analytics::kpi-card>
            <x-analytics::kpi-card :label="__('Conversions')" :value="number_format($conversions, 0, ',', ' ')" icon="check-circle" :metric="$conversionsDelta" />
            <x-analytics::kpi-card :label="__('Taux de conversion')" :value="$rateLabel" icon="arrow-trending-up" :metric="$rateDelta" />
        </div>
        <x-ui.card class="mt-6">
            <x-ui.section-header :title="__('Sessions au fil du temps')" class="mb-4" />
            @if (array_sum($trendData) > 0)
                <x-analytics::area-chart wire:key="mkt-atrend-{{ $range->days }}-{{ $subject }}" :labels="$trendLabels" :data="$trendData" :label="__('Sessions')" height="h-56" />
            @else
                <div class="flex h-56 items-center justify-center rounded-lg bg-elevated text-[12px] text-muted">{{ __('Aucune session sur la période.') }}</div>
            @endif
        </x-ui.card>
    </div>

    {{-- Objectives + conditions --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-ui.card>
            <x-ui.section-header :title="__('Objectifs de conversion')" :description="__('Conversions créditées à cette pub.')" class="mb-4" />
            @forelse ($ad->objectives as $objective)
                <div wire:key="obj-{{ $objective->id }}" class="flex items-center gap-2 py-1.5">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-elevated">
                        <x-ui.icon :name="$objective->type->value === 'funnel' ? 'funnel' : 'bolt'" class="h-3.5 w-3.5 text-secondary" />
                    </span>
                    <span class="min-w-0 flex-1 truncate text-[13px] text-primary">{{ $objectiveLabels[$objective->type->value.':'.$objective->reference] ?? $objective->reference }}</span>
                    <span class="shrink-0 text-[12px] tabular-nums"><span class="font-semibold text-primary">{{ number_format($objectiveConversions[$objective->reference] ?? 0, 0, ',', ' ') }}</span> <span class="text-muted">{{ __('conv.') }}</span></span>
                    <x-ui.badge :color="$objective->type->value === 'funnel' ? 'blue' : 'emerald'">{{ $objective->type->value === 'funnel' ? __('Tunnel') : __('Événement') }}</x-ui.badge>
                    @if ($objective->type->value === 'event')
                        <span class="text-[11px] text-muted tabular-nums">{{ rtrim(rtrim(number_format((float) $objective->value, 2, ',', ' '), '0'), ',') }} {{ __('pts') }}</span>
                    @endif
                </div>
            @empty
                <p class="py-4 text-[12px] text-muted">{{ __('Aucun objectif. Cette pub ne crédite aucune conversion.') }}</p>
            @endforelse
        </x-ui.card>

        <x-ui.card>
            <x-ui.section-header :title="__('Conditions d\'URL')" :description="__('La pub correspond si tous ces paramètres sont présents.')" class="mb-4" />
            <div class="flex flex-wrap items-center gap-1.5">
                @forelse ($ad->match_conditions ?? [] as $condition)
                    <x-analytics::condition-chip :param="$condition['param']" :value="$condition['value']" />
                @empty
                    <span class="inline-flex items-center gap-1 text-[11px] text-amber-600 dark:text-amber-400"><x-ui.icon name="exclamation-triangle" class="h-3.5 w-3.5" /> {{ __('Aucune condition : ne correspondra à aucun trafic') }}</span>
                @endforelse
            </div>
        </x-ui.card>
    </div>

    {{-- Ad edit modal --}}
    <div x-on:keydown.escape.window="$wire.modal !== '' && $wire.closeModal()">
        @include('analytics::livewire.dashboard.partials.marketing-ad-form')
    </div>

</div>
