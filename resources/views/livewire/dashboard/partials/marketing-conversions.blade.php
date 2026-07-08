@php
    $showAd = $showAd ?? true;
    $routeName = config('analytics.marketing.route_name', 'marketing');
@endphp

{{-- Sorted list of conversion elements (objectives): what converted, how many
     times, via which ad. A funnel objective expands into its step events. --}}
<div>
    <x-ui.section-header :title="__('Conversions')" :description="__('Ce qui a converti, combien de fois' . ($showAd ? ', et via quelle pub' : '') . ', du plus au moins converti.')" class="mb-4" />

    @if ($conversionElements === [])
        <x-ui.empty-state icon="check-circle" :title="__('Aucun objectif défini')" :description="__('Définis des objectifs sur tes pubs pour mesurer les conversions ici.')" />
    @else
        <x-ui.card padding="false">
            <div class="divide-y divide-subtle">
                @foreach ($conversionElements as $i => $el)
                    <div wire:key="conv-{{ $i }}" @if ($el['steps']) x-data="{ open: false }" @endif class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            @if ($el['steps'])
                                <button type="button" x-on:click="open = ! open" class="flex h-4 w-4 shrink-0 cursor-pointer items-center justify-center" aria-label="{{ __('Détail du tunnel') }}">
                                    <x-ui.icon name="chevron-right" class="h-4 w-4 text-muted transition-transform" x-bind:class="open && 'rotate-90'" />
                                </button>
                            @else
                                <span class="w-4 shrink-0"></span>
                            @endif
                            <x-ui.icon :name="$el['type'] === 'funnel' ? 'funnel' : 'bolt'" class="h-4 w-4 shrink-0 {{ $el['type'] === 'funnel' ? 'text-blue-500' : 'text-emerald-500' }}" />
                            <span class="min-w-0 flex-1 truncate text-[13px] font-medium text-primary">{{ $el['label'] }}</span>
                            <x-ui.badge :color="$el['type'] === 'funnel' ? 'blue' : 'emerald'">{{ $el['type'] === 'funnel' ? __('Tunnel') : __('Événement') }}</x-ui.badge>
                            @if ($showAd)
                                <a href="{{ route($routeName.'.ads.show', $el['adId']) }}" class="hidden w-32 shrink-0 cursor-pointer truncate text-right text-[12px] text-secondary hover:text-primary hover:underline sm:inline">{{ $el['adName'] }}</a>
                            @endif
                            <span class="w-16 shrink-0 text-right text-base font-semibold text-primary tabular-nums">{{ number_format($el['conversions'], 0, ',', ' ') }}</span>
                        </div>
                        @if ($el['steps'])
                            <div x-show="open" x-cloak class="ml-2 mt-3 space-y-1.5 border-l border-base pl-4">
                                <p class="text-[11px] font-medium uppercase tracking-wider text-muted">{{ __('Étapes du tunnel') }}</p>
                                @php $maxStep = collect($el['steps'])->max('count') ?: 1; @endphp
                                @foreach ($el['steps'] as $si => $step)
                                    <div class="flex items-center gap-3">
                                        <span class="w-44 shrink-0 truncate text-[12px] text-secondary">{{ $si + 1 }}. {{ $step['label'] }}</span>
                                        <div class="relative h-1.5 flex-1 overflow-hidden rounded-full bg-elevated">
                                            <div class="absolute inset-y-0 left-0 rounded-full bg-[#1684ea]/70" style="width: {{ max((int) round($step['count'] / $maxStep * 100), 2) }}%"></div>
                                        </div>
                                        <span class="w-10 shrink-0 text-right text-[12px] font-medium text-secondary tabular-nums">{{ number_format($step['count'], 0, ',', ' ') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    @endif
</div>
