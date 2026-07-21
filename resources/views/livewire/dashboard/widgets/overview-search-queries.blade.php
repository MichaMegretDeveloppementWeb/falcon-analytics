@php
    $maxClicks = max(array_column($queries, 'clicks') ?: [0]);
@endphp

<div>
    <div class="mb-4 flex items-end justify-between gap-4">
        <x-ui.section-header :title="__('Clics par recherches Google')" :description="__('Les termes réellement tapés sur Google, via Search Console')" />
        @if ($connected && $freshestDate !== null)
            <span class="shrink-0 whitespace-nowrap text-[11px] text-muted">{{ __('Données Google jusqu\'au :date', ['date' => $freshestDate->isoFormat('D MMM')]) }}</span>
        @endif
    </div>

    <x-ui.card>
        @if (! $connected)
            <div class="flex flex-col items-center gap-3 py-6 text-center">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-elevated">
                    <x-ui.icon name="magnifying-glass" class="h-5 w-5 text-secondary" />
                </span>
                <div>
                    <p class="text-[13px] font-medium text-primary">{{ __('Connectez Google Search Console') }}</p>
                    <p class="mt-1 text-[12px] text-secondary">{{ __('Google masque les mots-clés organiques au traceur ; seule la Search Console les fournit, au propriétaire vérifié du site.') }}</p>
                </div>
                <x-ui.button variant="secondary" :href="$integrationsRoute">
                    {{ $connectionStatus === \Falcon\Analytics\Models\SearchConsoleConnection::STATUS_ERROR ? __('Reconnecter Search Console') : __('Connecter Search Console') }}
                </x-ui.button>
            </div>
        @else
            @forelse ($queries as $item)
                @php $pct = $maxClicks > 0 ? round($item['clicks'] / $maxClicks * 100) : 0; @endphp
                <div class="flex items-center gap-3 py-1.5">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-elevated">
                        <x-ui.icon name="magnifying-glass" class="h-3.5 w-3.5 text-secondary" />
                    </span>
                    <span class="w-40 shrink-0 truncate text-[13px] text-primary sm:w-56" data-tooltip="{{ $item['query'] }}">{{ $item['query'] }}</span>
                    <div class="relative h-1.5 flex-1 overflow-hidden rounded-full bg-elevated">
                        <div class="absolute inset-y-0 left-0 rounded-full bg-[#1684ea]/70" style="width: {{ $pct }}%"></div>
                    </div>
                    <span class="w-10 shrink-0 text-right text-[12px] font-medium text-secondary" data-tooltip="{{ __('Clics') }}">{{ number_format($item['clicks'], 0, ',', ' ') }}</span>
                    <span class="hidden w-24 shrink-0 text-right text-[11px] text-muted sm:block">
                        {{ number_format($item['impressions'], 0, ',', ' ') }} {{ __('imp.') }}{{ $item['position'] !== null ? ' · '.__('pos.').' '.number_format($item['position'], 1, ',', ' ') : '' }}
                    </span>
                </div>
            @empty
                <x-ui.empty-state
                    icon="magnifying-glass"
                    :title="__('Aucune donnée sur la période')"
                    :description="__('La synchronisation est quotidienne et les données Google paraissent avec ~3 jours de décalage.')" />
            @endforelse
        @endif
    </x-ui.card>
</div>
