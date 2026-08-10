@php
    use Falcon\Analytics\Support\SourceLabel;

    $sourcesTotal = array_sum(array_column($topSources, 'total'));
    $localitiesTotal = array_sum(array_column($topLocalities, 'total'));
    $sourcePalette = ['#1684ea', '#54a8f0', '#7cb8f2', '#a5cdf6', '#bcdcfa', '#d7e9fc'];
@endphp

<x-ui.card>
    <div class="grid grid-cols-1 divide-y divide-subtle lg:grid-cols-2 lg:divide-x lg:divide-y-0">

        <div class="pb-5 lg:pb-0 lg:pr-8">
            <x-ui.section-header :title="__('Sources de trafic')" class="mb-4" />
            @if ($topSources !== [])
                <div class="flex items-center gap-6">
                    <div wire:key="donut-sources-{{ $period }}-{{ $subject }}">
                        <x-analytics::donut
                            :labels="collect($topSources)->map(fn ($s) => SourceLabel::for($s['label']))->all()"
                            :values="array_column($topSources, 'total')"
                            :colors="array_slice($sourcePalette, 0, count($topSources))"
                            :total="number_format($sourcesTotal, 0, ',', ' ')"
                            :caption="__('sessions')" />
                    </div>
                    {{-- Voir overview-audience : la colonne des nombres commence apres le plus
                         long libelle, et non au bord de la carte. --}}
                    <dl class="grid min-w-0 max-w-[19rem] flex-1 grid-cols-[minmax(0,1fr)_auto] items-center gap-x-5 gap-y-2.5">
                        @foreach ($topSources as $item)
                            <dt class="flex min-w-0 items-center gap-2 text-[13px] text-secondary">
                                <span class="h-2 w-2 shrink-0 rounded-full" style="background:{{ $sourcePalette[$loop->index] ?? '#d1d5db' }}"></span>
                                <span class="truncate"><x-analytics::source :value="$item['label']" /></span>
                            </dt>
                            <dd class="flex shrink-0 items-center gap-2">
                                @include('analytics::livewire.dashboard.partials.delta', ['current' => $item['total'], 'previous' => $item['previous']])
                                <span class="w-8 text-right text-[13px] font-semibold tabular-nums text-primary">{{ number_format($item['total'], 0, ',', ' ') }}</span>
                            </dd>
                        @endforeach
                    </dl>
                </div>
            @else
                <x-ui.empty-state icon="signal" :title="__('Aucune source')" :description="__('Aucune session sur la période.')" />
            @endif
        </div>

        <div class="pt-5 lg:pl-8 lg:pt-0">
            <x-ui.section-header :title="__('Localités')" class="mb-4" />
            @if ($topLocalities !== [])
                {{--
                    Une figure d'entree, comme le beignet en donne une a son voisin. Sans elle
                    ce bloc s'ouvrait sur une liste sans point d'accroche : c'est le contraste
                    de taille qui cree la hierarchie, aucune zone ne se signalait.
                --}}
                <p class="text-2xl font-semibold tracking-tight text-primary">{{ number_format($localitiesTotal, 0, ',', ' ') }}</p>
                <p class="mb-4 text-[11px] uppercase tracking-wider text-muted">{{ __('sessions localisées') }}</p>

                <dl class="grid max-w-[22rem] grid-cols-[minmax(0,1fr)_auto] items-center gap-x-5 gap-y-2.5">
                    @foreach ($topLocalities as $item)
                        <dt class="min-w-0 truncate text-[13px] text-secondary">
                            <x-analytics::country :code="$item['country']" :city="$item['city']" />
                        </dt>
                        <dd class="flex shrink-0 items-center gap-2">
                            @include('analytics::livewire.dashboard.partials.delta', ['current' => $item['total'], 'previous' => $item['previous']])
                            <span class="w-8 text-right text-[13px] font-semibold tabular-nums text-primary">{{ number_format($item['total'], 0, ',', ' ') }}</span>
                        </dd>
                    @endforeach
                </dl>
            @else
                <x-ui.empty-state icon="globe-alt" :title="__('Aucune localité')" :description="__('Géolocalisation indisponible.')" />
            @endif
        </div>

    </div>
</x-ui.card>
