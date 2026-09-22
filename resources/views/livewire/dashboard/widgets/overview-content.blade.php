@php
    use Falcon\Analytics\Support\NumberLabel;

    $maxPages = max(array_column($topPages, 'total') ?: [0]);
@endphp

{{-- The root wraps the card rather than replacing it: the card is a component
     of the kit, and it is precisely the one that has to be drawn inside the
     package's context. --}}
<x-analytics::root area="admin">
<x-ui::card>
    <div class="an:grid an:grid-cols-1 an:divide-y an:divide-subtle an:lg:grid-cols-2 an:lg:divide-x an:lg:divide-y-0">

        <div class="an:pb-5 an:lg:pb-0 an:lg:pr-8">
            <x-ui::section-header :title="__('Pages les plus vues')" class="an:mb-4" />
            <div class="an:space-y-3">
                @forelse ($topPages as $item)
                    @php $pct = $maxPages > 0 ? max(round($item['total'] / $maxPages * 100), 2) : 0; @endphp
                    <div>
                        <div class="an:flex an:items-center an:justify-between an:gap-4">
                            <span class="an:min-w-0 an:truncate an:text-[13px] an:text-primary"><x-analytics::page-url :url="$item['label']" /></span>
                            <span class="an:flex an:shrink-0 an:items-center an:gap-2">
                                @include('analytics::livewire.dashboard.partials.delta', ['current' => $item['total'], 'previous' => $item['previous']])
                                <span class="an:w-8 an:text-right an:text-[13px] an:font-semibold an:text-primary">{{ NumberLabel::for($item['total']) }}</span>
                            </span>
                        </div>
                        <div class="an:mt-1.5 an:h-1 an:overflow-hidden an:rounded-full an:bg-elevated">
                            <div class="an:h-full an:rounded-full an:bg-series-1/70" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @empty
                    <x-ui::empty-state icon="document" :title="__('Aucune page')" :description="__('Aucune vue sur la période.')" />
                @endforelse
            </div>
        </div>

        <div class="an:pt-5 an:lg:pl-8 an:lg:pt-0">
            <x-ui::section-header :title="__('Clics principaux')" class="an:mb-4" />
            @if ($topClicks !== [])
                {{-- The label already runs to two lines: the count column follows it
                     closely enough, without truncating it further. --}}
                <dl class="an:grid an:max-w-[26rem] an:grid-cols-[minmax(0,1fr)_auto] an:items-center an:gap-x-5 an:gap-y-3">
                    @foreach ($topClicks as $click)
                        <dt class="an:min-w-0">
                            <span class="an:block an:truncate an:text-[13px] an:text-secondary" data-an-tooltip="{{ $click['label'] }}">{{ $click['label'] }}</span>
                            @if ($click['route'])
                                <span class="an:block an:truncate an:text-[11px] an:text-muted"><x-analytics::page-url :route="$click['route']" /></span>
                            @endif
                        </dt>
                        <dd class="an:w-8 an:text-right an:text-[13px] an:font-semibold an:tabular-nums an:text-primary">{{ NumberLabel::for($click['total']) }}</dd>
                    @endforeach
                </dl>
            @else
                <x-ui::empty-state icon="cursor-arrow-rays" :title="__('Aucun clic')" :description="__('Aucun clic capté sur la période.')" />
            @endif
        </div>

    </div>
</x-ui::card>
</x-analytics::root>
