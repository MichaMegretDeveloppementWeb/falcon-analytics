@php
    $conversionsTotal = array_sum(array_column($topConversions, 'count'));
@endphp

<div class="border-t border-base pt-8">
    <div class="mb-4 flex items-end justify-between gap-4">
        <x-ui.section-header :title="__('Événements & conversions')" />
        <a href="{{ $eventsRoute }}" class="shrink-0 cursor-pointer whitespace-nowrap text-[12px] font-medium text-secondary transition-colors hover:text-primary">{{ __('Voir tout') }} <span aria-hidden="true">&rarr;</span></a>
    </div>

    <x-ui.card>
        <div class="grid grid-cols-1 divide-y divide-subtle lg:grid-cols-2 lg:divide-x lg:divide-y-0">

            <div class="pb-5 lg:pb-0 lg:pr-8">
                <x-ui.section-header :title="__('Conversions')" class="mb-4" />
                @if ($topConversions !== [])
                    <p class="text-2xl font-semibold tracking-tight text-primary">{{ number_format($conversionsTotal, 0, ',', ' ') }}</p>
                    <p class="mb-4 text-[11px] uppercase tracking-wider text-muted">{{ __('sur la période') }}</p>

                    {{-- La part passe en infobulle : deux nombres de taille voisine cote a cote,
                         sans separateur, obligeaient a decider lequel on lit. --}}
                    <dl class="grid max-w-[22rem] grid-cols-[minmax(0,1fr)_auto] items-center gap-x-5 gap-y-2.5">
                        @foreach ($topConversions as $item)
                            <dt class="flex min-w-0 items-center gap-2.5">
                                <span class="h-2 w-2 shrink-0 rounded-full bg-emerald-500 dark:bg-emerald-400"></span>
                                <span class="truncate text-[13px] text-secondary" data-tooltip="{{ $item['label'] }}">{{ $item['label'] }}</span>
                            </dt>
                            <dd class="w-8 text-right text-[13px] font-semibold tabular-nums text-primary"
                                title="{{ $conversionsTotal > 0 ? ((int) round($item['count'] / $conversionsTotal * 100))."\u{00A0}%" : '' }}">{{ number_format($item['count'], 0, ',', ' ') }}</dd>
                        @endforeach
                    </dl>
                @else
                    <x-ui.empty-state icon="check-circle" :title="__('Aucune conversion')" :description="__('Aucune conversion sur la période.')" />
                @endif
            </div>

            <div class="pt-5 lg:pl-8 lg:pt-0">
                <x-ui.section-header :title="__('Top événements')" class="mb-4" />
                @if ($topEvents !== [])
                    <dl class="grid max-w-[22rem] grid-cols-[minmax(0,1fr)_auto] items-center gap-x-5 gap-y-2.5">
                        @foreach ($topEvents as $item)
                            <dt class="flex min-w-0 items-center gap-2.5">
                                <span class="w-5 shrink-0 text-[11px] font-medium tabular-nums text-muted">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                <span class="truncate text-[13px] text-secondary" data-tooltip="{{ $item['label'] }}">{{ $item['label'] }}</span>
                            </dt>
                            <dd class="w-8 text-right text-[13px] font-semibold tabular-nums text-primary">{{ number_format($item['count'], 0, ',', ' ') }}</dd>
                        @endforeach
                    </dl>
                @else
                    <x-ui.empty-state icon="bolt" :title="__('Aucun événement')" :description="__('Aucun événement sur la période.')" />
                @endif
            </div>

        </div>
    </x-ui.card>
</div>
