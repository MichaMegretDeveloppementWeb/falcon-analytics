@php
    $maxPages = max(array_column($topPages, 'total') ?: [0]);
@endphp

<x-ui.card>
    <div class="grid grid-cols-1 divide-y divide-subtle lg:grid-cols-2 lg:divide-x lg:divide-y-0">

        <div class="pb-5 lg:pb-0 lg:pr-8">
            <x-ui.section-header :title="__('Pages les plus vues')" class="mb-4" />
            <div class="space-y-3">
                @forelse ($topPages as $item)
                    @php $pct = $maxPages > 0 ? max(round($item['total'] / $maxPages * 100), 2) : 0; @endphp
                    <div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="min-w-0 truncate text-[13px] text-primary"><x-analytics::page-url :url="$item['label']" /></span>
                            <span class="flex shrink-0 items-center gap-2">
                                @include('analytics::livewire.dashboard.partials.delta', ['current' => $item['total'], 'previous' => $item['previous']])
                                <span class="w-8 text-right text-[13px] font-semibold text-primary">{{ number_format($item['total'], 0, ',', ' ') }}</span>
                            </span>
                        </div>
                        <div class="mt-1.5 h-1 overflow-hidden rounded-full bg-elevated">
                            <div class="h-full rounded-full bg-[#1684ea]/70" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @empty
                    <x-ui.empty-state icon="document" :title="__('Aucune page')" :description="__('Aucune vue sur la période.')" />
                @endforelse
            </div>
        </div>

        <div class="pt-5 lg:pl-8 lg:pt-0">
            <x-ui.section-header :title="__('Clics principaux')" class="mb-4" />
            @forelse ($topClicks as $click)
                <div class="flex items-center justify-between gap-4 py-[7px]">
                    <div class="min-w-0">
                        <p class="truncate text-[13px] text-primary" data-tooltip="{{ $click['label'] }}">{{ $click['label'] }}</p>
                        @if ($click['route'])
                            <p class="truncate text-[11px] text-muted"><x-analytics::page-url :route="$click['route']" /></p>
                        @endif
                    </div>
                    <span class="shrink-0 text-[13px] font-semibold text-primary">{{ number_format($click['total'], 0, ',', ' ') }}</span>
                </div>
            @empty
                <x-ui.empty-state icon="cursor-arrow-rays" :title="__('Aucun clic')" :description="__('Aucun clic capté sur la période.')" />
            @endforelse
        </div>

    </div>
</x-ui.card>
