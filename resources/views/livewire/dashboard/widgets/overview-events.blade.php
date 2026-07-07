@php
    $maxConversions = max(array_column($topConversions, 'count') ?: [0]);
    $maxEvents = max(array_column($topEvents, 'count') ?: [0]);
@endphp

<div>
    <div class="mb-4 flex items-end justify-between gap-4">
        <x-ui.section-header :title="__('Événements & conversions')" :description="__('Les actions clés déclenchées sur le site')" />
        <a href="{{ $eventsRoute }}" class="shrink-0 whitespace-nowrap text-[12px] font-medium text-secondary transition-colors hover:text-primary">{{ __('Voir tout') }} <span aria-hidden="true">&rarr;</span></a>
    </div>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        <x-ui.card>
            <x-ui.section-header :title="__('Top conversions')" :description="__('Événements clés')" class="mb-4" />
            @forelse ($topConversions as $item)
                @php $pct = $maxConversions > 0 ? round($item['count'] / $maxConversions * 100) : 0; @endphp
                <div class="flex items-center gap-3 py-1.5">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-500/10">
                        <x-ui.icon name="check-circle" class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                    </span>
                    <span class="w-40 shrink-0 truncate text-[13px] text-primary" data-tooltip="{{ $item['label'] }}">{{ $item['label'] }}</span>
                    <div class="relative h-1.5 flex-1 overflow-hidden rounded-full bg-elevated">
                        <div class="absolute inset-y-0 left-0 rounded-full bg-emerald-500/70" style="width: {{ $pct }}%"></div>
                    </div>
                    <span class="w-10 shrink-0 text-right text-[12px] font-medium text-secondary">{{ number_format($item['count'], 0, ',', ' ') }}</span>
                </div>
            @empty
                <x-ui.empty-state icon="check-circle" :title="__('Aucune conversion')" :description="__('Aucune conversion sur la période.')" />
            @endforelse
        </x-ui.card>

        <x-ui.card>
            <x-ui.section-header :title="__('Top événements')" :description="__('Toutes actions confondues')" class="mb-4" />
            @forelse ($topEvents as $item)
                @php $pct = $maxEvents > 0 ? round($item['count'] / $maxEvents * 100) : 0; @endphp
                <div class="flex items-center gap-3 py-1.5">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-elevated">
                        <x-ui.icon name="bolt" class="h-3.5 w-3.5 text-secondary" />
                    </span>
                    <span class="w-40 shrink-0 truncate text-[13px] text-primary" data-tooltip="{{ $item['label'] }}">{{ $item['label'] }}</span>
                    <div class="relative h-1.5 flex-1 overflow-hidden rounded-full bg-elevated">
                        <div class="absolute inset-y-0 left-0 rounded-full bg-[#1684ea]/70" style="width: {{ $pct }}%"></div>
                    </div>
                    <span class="w-10 shrink-0 text-right text-[12px] font-medium text-secondary">{{ number_format($item['count'], 0, ',', ' ') }}</span>
                </div>
            @empty
                <x-ui.empty-state icon="bolt" :title="__('Aucun événement')" :description="__('Aucun événement sur la période.')" />
            @endforelse
        </x-ui.card>

    </div>
</div>
