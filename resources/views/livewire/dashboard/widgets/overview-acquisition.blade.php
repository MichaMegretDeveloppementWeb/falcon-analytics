@php
    use Falcon\Analytics\Support\SourceLabel;

    $sourcesTotal = array_sum(array_column($topSources, 'total'));
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
                    <div class="min-w-0 flex-1 space-y-2.5">
                        @foreach ($topSources as $item)
                            <div class="flex items-center justify-between gap-2">
                                <span class="flex min-w-0 items-center gap-2 text-[13px] text-secondary">
                                    <span class="h-2 w-2 shrink-0 rounded-full" style="background:{{ $sourcePalette[$loop->index] ?? '#d1d5db' }}"></span>
                                    <span class="truncate"><x-analytics::source :value="$item['label']" /></span>
                                </span>
                                <span class="flex shrink-0 items-center gap-2">
                                    @include('analytics::livewire.dashboard.partials.delta', ['current' => $item['total'], 'previous' => $item['previous']])
                                    <span class="w-8 text-right text-[13px] font-semibold text-primary">{{ number_format($item['total'], 0, ',', ' ') }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <x-ui.empty-state icon="signal" :title="__('Aucune source')" :description="__('Aucune session sur la période.')" />
            @endif
        </div>

        <div class="pt-5 lg:pl-8 lg:pt-0">
            <x-ui.section-header :title="__('Localités')" class="mb-4" />
            @forelse ($topLocalities as $item)
                <div class="flex items-center justify-between gap-4 py-[7px]">
                    <span class="min-w-0 truncate text-[13px] text-primary">
                        <x-analytics::country :code="$item['country']" :city="$item['city']" />
                    </span>
                    <span class="flex shrink-0 items-center gap-2">
                        @include('analytics::livewire.dashboard.partials.delta', ['current' => $item['total'], 'previous' => $item['previous']])
                        <span class="w-8 text-right text-[13px] font-semibold text-primary">{{ number_format($item['total'], 0, ',', ' ') }}</span>
                    </span>
                </div>
            @empty
                <x-ui.empty-state icon="globe-alt" :title="__('Aucune localité')" :description="__('Géolocalisation indisponible.')" />
            @endforelse
        </div>

    </div>
</x-ui.card>
