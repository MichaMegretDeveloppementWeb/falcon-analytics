<div>
    <div class="mb-4 flex items-end justify-between gap-4">
        <x-ui.section-header :title="__('Clics par recherches Google')" />
        @if ($connected && $freshestDate !== null)
            <span class="shrink-0 whitespace-nowrap text-[11px] text-muted">{{ __('Dernières données Google : :date', ['date' => $freshestDate->isoFormat('D MMM')]) }}</span>
        @endif
    </div>

    <x-ui.card>
        @if (! $connected)
            <div class="flex flex-col items-center gap-3 py-6 text-center">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-elevated">
                    <x-ui.icon name="magnifying-glass" class="h-5 w-5 text-secondary" />
                </span>
                <p class="max-w-md text-[12px] text-secondary">
                    {{ __('Connectez Google Search Console pour suivre les recherches qui mènent au site : clics, impressions et position.') }}
                </p>
                <x-ui.button variant="secondary" :href="$integrationsRoute">
                    {{ $connectionStatus === \Falcon\Analytics\Models\SearchConsoleConnection::STATUS_ERROR ? __('Reconnecter Search Console') : __('Connecter Search Console') }}
                </x-ui.button>
            </div>
        @elseif ($queries === [])
            <x-ui.empty-state
                icon="magnifying-glass"
                :title="__('Aucune donnée sur la période')"
                :description="__('Les données Google paraissent avec quelques jours de décalage.')" />
        @else
            <div class="flex items-baseline gap-2 border-b border-subtle pb-4">
                <span class="text-2xl font-semibold tracking-tight text-primary">{{ number_format($totals['current'], 0, ',', ' ') }}</span>
                @include('analytics::livewire.dashboard.partials.delta', ['current' => $totals['current'], 'previous' => $totals['previous']])
                <span class="text-[12px] text-muted">{{ __('clics sur la période') }}</span>
            </div>

            <div class="grid grid-cols-1 gap-x-10 pt-2 lg:grid-cols-2">
                @foreach ($queries as $item)
                    <div class="flex items-center justify-between gap-4 border-b border-subtle py-2.5 lg:[&:nth-last-child(-n+2)]:border-b-0 [&:last-child]:border-b-0">
                        <div class="min-w-0">
                            <p class="truncate text-[13px] font-medium text-primary" data-tooltip="{{ $item['query'] }}">{{ $item['query'] }}</p>
                            <p class="text-[11px] text-muted">
                                {{ __('Position moy. : :position', ['position' => $item['position'] !== null ? number_format($item['position'], 1, ',', ' ') : '–']) }}
                                · {{ number_format($item['impressions'], 0, ',', ' ') }} {{ __('impressions') }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @include('analytics::livewire.dashboard.partials.delta', ['current' => $item['clicks'], 'previous' => $item['previous']])
                            <span class="w-8 text-right text-[13px] font-semibold text-primary">{{ number_format($item['clicks'], 0, ',', ' ') }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.card>
</div>
