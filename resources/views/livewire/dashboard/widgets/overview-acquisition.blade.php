@php
    use Falcon\Analytics\Support\ChartPalette;
    use Falcon\Analytics\Support\NumberLabel;
    use Falcon\Analytics\Support\SourceLabel;

    $sourcesTotal = array_sum(array_column($topSources, 'total'));
    $localitiesTotal = array_sum(array_column($topLocalities, 'total'));
    $sourcePalette = ChartPalette::SERIES;
@endphp

{{-- The root wraps the card rather than replacing it: the card is a component
     of the kit, and it is precisely the one that has to be drawn inside the
     package's context. --}}
<x-analytics::root area="admin">
<x-ui::card>
    <div class="an:grid an:grid-cols-1 an:divide-y an:divide-subtle an:lg:grid-cols-2 an:lg:divide-x an:lg:divide-y-0">

        <div class="an:pb-5 an:lg:pb-0 an:lg:pr-8">
            <x-ui::section-header :title="__('Sources de trafic')" class="an:mb-4" />
            @include('analytics::livewire.dashboard.partials.attribution-ceiling', ['class' => 'an:mb-4'])
            @if ($topSources !== [])
                <div class="an:flex an:items-center an:gap-6">
                    <div wire:key="donut-sources-{{ $period }}-{{ $subject }}">
                        <x-analytics::donut
                            :labels="collect($topSources)->map(fn ($s) => SourceLabel::for($s['label']))->all()"
                            :values="array_column($topSources, 'total')"
                            :colors="array_slice($sourcePalette, 0, count($topSources))"
                            :total="NumberLabel::for($sourcesTotal)"
                            :caption="$sourcesTotal > 1 ? __('sessions') : __('session')" />
                    </div>
                    <dl class="an:grid an:min-w-0 an:max-w-[19rem] an:flex-1 an:grid-cols-[minmax(0,1fr)_auto] an:items-center an:gap-x-5 an:gap-y-2.5">
                        @foreach ($topSources as $item)
                            <dt class="an:flex an:min-w-0 an:items-center an:gap-2 an:text-[13px] an:text-secondary">
                                <span class="an:h-2 an:w-2 an:shrink-0 an:rounded-full" style="background:var({{ $sourcePalette[$loop->index] ?? '--an-series-6' }})"></span>
                                <span class="an:truncate"><x-analytics::source :value="$item['label']" /></span>
                            </dt>
                            <dd class="an:flex an:shrink-0 an:items-center an:gap-2">
                                @include('analytics::livewire.dashboard.partials.delta', ['current' => $item['total'], 'previous' => $item['previous']])
                                <span class="an:w-8 an:text-right an:text-[13px] an:font-semibold an:tabular-nums an:text-primary">{{ NumberLabel::for($item['total']) }}</span>
                            </dd>
                        @endforeach
                    </dl>
                </div>
            @else
                <x-ui::empty-state icon="signal" :title="__('Aucune source')" :description="__('Aucune session sur la période.')" />
            @endif
        </div>

        <div class="an:pt-5 an:lg:pl-8 an:lg:pt-0">
            <x-ui::section-header :title="__('Localités')" class="an:mb-4" />
            @if ($topLocalities !== [])
                {{--
                    A figure to open on, the way the doughnut gives its neighbour one: it is
                    the contrast in size that makes the hierarchy, and a list alone has
                    nothing to catch the eye.
                --}}
                <p class="an:text-2xl an:font-semibold an:tracking-tight an:text-primary">{{ NumberLabel::for($localitiesTotal) }}</p>
                <p class="an:mb-4 an:text-[11px] an:uppercase an:tracking-wider an:text-muted">{{ $localitiesTotal > 1 ? __('sessions localisées') : __('session localisée') }}</p>

                <dl class="an:grid an:max-w-[22rem] an:grid-cols-[minmax(0,1fr)_auto] an:items-center an:gap-x-5 an:gap-y-2.5">
                    @foreach ($topLocalities as $item)
                        <dt class="an:min-w-0 an:truncate an:text-[13px] an:text-secondary">
                            <x-analytics::country :code="$item['country']" :city="$item['city']" />
                        </dt>
                        <dd class="an:flex an:shrink-0 an:items-center an:gap-2">
                            @include('analytics::livewire.dashboard.partials.delta', ['current' => $item['total'], 'previous' => $item['previous']])
                            <span class="an:w-8 an:text-right an:text-[13px] an:font-semibold an:tabular-nums an:text-primary">{{ NumberLabel::for($item['total']) }}</span>
                        </dd>
                    @endforeach
                </dl>
            @else
                <x-ui::empty-state icon="globe-alt" :title="__('Aucune localité')" :description="__('Géolocalisation indisponible.')" />
            @endif
        </div>

    </div>
</x-ui::card>
</x-analytics::root>
