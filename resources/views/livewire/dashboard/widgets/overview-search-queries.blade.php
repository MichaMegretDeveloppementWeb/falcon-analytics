@php use Falcon\Analytics\Support\NumberLabel; @endphp

<x-analytics::root area="admin">
    <div class="an:mb-4 an:flex an:items-end an:justify-between an:gap-4">
        <x-ui::section-header :title="__('Clics par recherches Google')" />
        @if ($connected && $freshestDate !== null)
            <span class="an:shrink-0 an:whitespace-nowrap an:text-[11px] an:text-muted">{{ __('Dernières données Google : :date', ['date' => $freshestDate->isoFormat('D MMM')]) }}</span>
        @endif
    </div>

    <x-ui::card>
        @if (! $connected)
            <div class="an:flex an:flex-col an:items-center an:gap-3 an:py-6 an:text-center">
                <span class="an:flex an:h-10 an:w-10 an:items-center an:justify-center an:rounded-lg an:bg-elevated">
                    <x-ui::icon name="magnifying-glass" class="an:h-5 an:w-5 an:text-secondary" />
                </span>
                <p class="an:max-w-md an:text-[12px] an:text-secondary">
                    {{ __('Connectez Google Search Console pour suivre les recherches qui mènent au site : clics, impressions et position.') }}
                </p>
                <x-ui::button variant="secondary" :href="$integrationsRoute">
                    {{ $connectionStatus === \Falcon\Analytics\Models\SearchConsoleConnection::STATUS_ERROR ? __('Reconnecter Search Console') : __('Connecter Search Console') }}
                </x-ui::button>
            </div>
        @elseif ($queries === [])
            <x-ui::empty-state
                icon="magnifying-glass"
                :title="__('Aucune donnée sur la période')"
                :description="__('Les données Google paraissent avec quelques jours de décalage.')" />
        @else
            <div class="an:flex an:items-baseline an:gap-2 an:border-b an:border-subtle an:pb-4">
                <span class="an:text-2xl an:font-semibold an:tracking-tight an:text-primary">{{ NumberLabel::for($totals['current']) }}</span>
                @include('analytics::livewire.dashboard.partials.delta', ['current' => $totals['current'], 'previous' => $totals['previous']])
                <span class="an:text-[12px] an:text-muted">{{ __('clics sur la période') }}</span>
            </div>

            <div class="an:grid an:grid-cols-1 an:gap-x-10 an:pt-2 an:lg:grid-cols-2">
                @foreach ($queries as $item)
                    <div class="an:flex an:items-center an:justify-between an:gap-4 an:border-b an:border-subtle an:py-2.5 an:lg:[&:nth-last-child(-n+2)]:border-b-0 an:[&:last-child]:border-b-0">
                        <div class="an:min-w-0">
                            <p class="an:truncate an:text-[13px] an:font-medium an:text-primary" data-an-tooltip="{{ $item['query'] }}">{{ $item['query'] }}</p>
                            <p class="an:text-[11px] an:text-muted">
                                {{ __('Position moy. : :position', ['position' => $item['position'] !== null ? NumberLabel::for($item['position'], 1) : '–']) }}
                                · {{ NumberLabel::for($item['impressions']) }} {{ __('impressions') }}
                            </p>
                        </div>
                        <div class="an:flex an:shrink-0 an:items-center an:gap-2">
                            @include('analytics::livewire.dashboard.partials.delta', ['current' => $item['clicks'], 'previous' => $item['previous']])
                            <span class="an:w-8 an:text-right an:text-[13px] an:font-semibold an:text-primary">{{ NumberLabel::for($item['clicks']) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui::card>
</x-analytics::root>
