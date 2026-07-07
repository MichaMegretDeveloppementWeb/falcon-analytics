@php
    $maxPages = max(array_column($topPages, 'total') ?: [0]);
@endphp

<div class="grid grid-cols-1 gap-6 lg:grid-cols-12">

    <x-ui.card class="lg:col-span-7">
        <x-ui.section-header :title="__('Pages les plus vues')" :description="__('Les plus consultées')" class="mb-4" />
        @forelse ($topPages as $item)
            @php $pct = $maxPages > 0 ? round($item['total'] / $maxPages * 100) : 0; @endphp
            <div class="flex items-center gap-3 py-1.5">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-elevated">
                    <x-ui.icon name="document-text" class="h-3.5 w-3.5 text-secondary" />
                </span>
                <span class="w-40 shrink-0 truncate text-[13px] text-primary"><x-analytics::page-url :url="$item['label']" /></span>
                <div class="relative h-1.5 flex-1 overflow-hidden rounded-full bg-elevated">
                    <div class="absolute inset-y-0 left-0 rounded-full bg-[#1684ea]/70" style="width: {{ $pct }}%"></div>
                </div>
                @include('analytics::livewire.dashboard.partials.delta', ['current' => $item['total'], 'previous' => $item['previous']])
                <span class="w-10 shrink-0 text-right text-[12px] font-medium text-secondary">{{ number_format($item['total'], 0, ',', ' ') }}</span>
            </div>
        @empty
            <x-ui.empty-state icon="document" :title="__('Aucune page')" :description="__('Aucune vue sur la période.')" />
        @endforelse
    </x-ui.card>

    <x-ui.card class="lg:col-span-5">
        <x-ui.section-header :title="__('Clics principaux')" :description="__('Boutons et liens cliqués')" class="mb-4" />
        @forelse ($topClicks as $click)
            <div class="flex items-center gap-3 py-1.5">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-elevated">
                    <x-ui.icon name="cursor-arrow-rays" class="h-3.5 w-3.5 text-secondary" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-[13px] text-primary" data-tooltip="{{ $click['label'] }}">{{ $click['label'] }}</p>
                    @if ($click['route'])
                        <p class="truncate text-[11px] text-muted"><x-analytics::page-url :route="$click['route']" /></p>
                    @endif
                </div>
                <span class="shrink-0 text-[12px] font-medium text-secondary">{{ number_format($click['total'], 0, ',', ' ') }}</span>
            </div>
        @empty
            <x-ui.empty-state icon="cursor-arrow-rays" :title="__('Aucun clic')" :description="__('Aucun clic capté sur la période.')" />
        @endforelse
    </x-ui.card>

</div>
