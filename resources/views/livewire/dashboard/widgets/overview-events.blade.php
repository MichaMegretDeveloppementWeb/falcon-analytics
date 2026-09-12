@php
    $conversionsTotal = array_sum(array_column($topConversions, 'count'));
@endphp

<x-analytics::root area="admin" class="an:border-t an:border-base an:pt-8">
    <div class="an:mb-4 an:flex an:items-end an:justify-between an:gap-4">
        <x-ui::section-header :title="__('Événements & conversions')" />
        <a href="{{ $eventsRoute }}" class="an:shrink-0 an:cursor-pointer an:whitespace-nowrap an:text-[12px] an:font-medium an:text-secondary an:transition-colors an:hover:text-primary">{{ __('Voir tout') }} <span aria-hidden="true">&rarr;</span></a>
    </div>

    <x-ui::card>
        <div class="an:grid an:grid-cols-1 an:divide-y an:divide-subtle an:lg:grid-cols-2 an:lg:divide-x an:lg:divide-y-0">

            <div class="an:pb-5 an:lg:pb-0 an:lg:pr-8">
                <x-ui::section-header :title="__('Conversions')" class="an:mb-4" />
                @if ($topConversions !== [])
                    <p class="an:text-2xl an:font-semibold an:tracking-tight an:text-primary">{{ number_format($conversionsTotal, 0, ',', ' ') }}</p>
                    <p class="an:mb-4 an:text-[11px] an:uppercase an:tracking-wider an:text-muted">{{ __('sur la période') }}</p>

                    {{-- The share moves into a tooltip: two numbers of similar size side by
                         side, with no separator, forced a decision on which one to read. --}}
                    <dl class="an:grid an:max-w-[22rem] an:grid-cols-[minmax(0,1fr)_auto] an:items-center an:gap-x-5 an:gap-y-2.5">
                        @foreach ($topConversions as $item)
                            <dt class="an:flex an:min-w-0 an:items-center an:gap-2.5">
                                <span class="an:h-2 an:w-2 an:shrink-0 an:rounded-full an:bg-emerald-500 an:dark:bg-emerald-400"></span>
                                <span class="an:truncate an:text-[13px] an:text-secondary" data-tooltip="{{ $item['label'] }}">{{ $item['label'] }}</span>
                            </dt>
                            <dd class="an:w-8 an:text-right an:text-[13px] an:font-semibold an:tabular-nums an:text-primary"
                                title="{{ $conversionsTotal > 0 ? ((int) round($item['count'] / $conversionsTotal * 100))."\u{00A0}%" : '' }}">{{ number_format($item['count'], 0, ',', ' ') }}</dd>
                        @endforeach
                    </dl>
                @else
                    <x-ui::empty-state icon="check-circle" :title="__('Aucune conversion')" :description="__('Aucune conversion sur la période.')" />
                @endif
            </div>

            <div class="an:pt-5 an:lg:pl-8 an:lg:pt-0">
                <x-ui::section-header :title="__('Top événements')" class="an:mb-4" />
                @if ($topEvents !== [])
                    <dl class="an:grid an:max-w-[22rem] an:grid-cols-[minmax(0,1fr)_auto] an:items-center an:gap-x-5 an:gap-y-2.5">
                        @foreach ($topEvents as $item)
                            <dt class="an:flex an:min-w-0 an:items-center an:gap-2.5">
                                <span class="an:w-5 an:shrink-0 an:text-[11px] an:font-medium an:tabular-nums an:text-muted">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                <span class="an:truncate an:text-[13px] an:text-secondary" data-tooltip="{{ $item['label'] }}">{{ $item['label'] }}</span>
                            </dt>
                            <dd class="an:w-8 an:text-right an:text-[13px] an:font-semibold an:tabular-nums an:text-primary">{{ number_format($item['count'], 0, ',', ' ') }}</dd>
                        @endforeach
                    </dl>
                @else
                    <x-ui::empty-state icon="bolt" :title="__('Aucun événement')" :description="__('Aucun événement sur la période.')" />
                @endif
            </div>

        </div>
    </x-ui::card>
</x-analytics::root>
