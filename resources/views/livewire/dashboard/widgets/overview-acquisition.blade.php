@php
    $maxSources = max(array_column($topSources, 'total') ?: [0]);
    $maxLocalities = max(array_column($topLocalities, 'total') ?: [0]);

    $sourceIcon = fn (string $category): string => [
        'direct' => 'cursor-arrow-rays',
        'organic' => 'magnifying-glass',
        'social' => 'user-group',
        'paid' => 'megaphone',
        'referral' => 'arrow-top-right-on-square',
        'email' => 'envelope',
        'campaign' => 'flag',
    ][strtolower($category)] ?? 'globe-alt';
@endphp

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

    <x-ui.card>
        <x-ui.section-header :title="__('Sources')" :description="__('Par canal d\'acquisition')" class="mb-4" />
        @forelse ($topSources as $item)
            @php $pct = $maxSources > 0 ? round($item['total'] / $maxSources * 100) : 0; @endphp
            <div class="flex items-center gap-3 py-1.5">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-elevated">
                    <x-ui.icon :name="$sourceIcon($item['label'])" class="h-3.5 w-3.5 text-secondary" />
                </span>
                <span class="w-36 shrink-0 truncate text-[13px] text-primary"><x-analytics::source :value="$item['label']" /></span>
                <div class="relative h-1.5 flex-1 overflow-hidden rounded-full bg-elevated">
                    <div class="absolute inset-y-0 left-0 rounded-full bg-[#1684ea]/70" style="width: {{ $pct }}%"></div>
                </div>
                @include('analytics::livewire.dashboard.partials.delta', ['current' => $item['total'], 'previous' => $item['previous']])
                <span class="w-10 shrink-0 text-right text-[12px] font-medium text-secondary">{{ number_format($item['total'], 0, ',', ' ') }}</span>
            </div>
        @empty
            <x-ui.empty-state icon="signal" :title="__('Aucune source')" :description="__('Aucune session sur la période.')" />
        @endforelse
    </x-ui.card>

    <x-ui.card>
        <x-ui.section-header :title="__('Localités')" :description="__('Pays et ville')" class="mb-4" />
        @forelse ($topLocalities as $item)
            @php $pct = $maxLocalities > 0 ? round($item['total'] / $maxLocalities * 100) : 0; @endphp
            <div class="flex items-center gap-3 py-1.5">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-elevated">
                    <x-ui.icon name="map-pin" class="h-3.5 w-3.5 text-secondary" />
                </span>
                <span class="w-36 shrink-0 truncate text-[13px] text-primary">
                    <x-analytics::country :code="$item['country']" :city="$item['city']" />
                </span>
                <div class="relative h-1.5 flex-1 overflow-hidden rounded-full bg-elevated">
                    <div class="absolute inset-y-0 left-0 rounded-full bg-[#1684ea]/70" style="width: {{ $pct }}%"></div>
                </div>
                @include('analytics::livewire.dashboard.partials.delta', ['current' => $item['total'], 'previous' => $item['previous']])
                <span class="w-10 shrink-0 text-right text-[12px] font-medium text-secondary">{{ number_format($item['total'], 0, ',', ' ') }}</span>
            </div>
        @empty
            <x-ui.empty-state icon="globe-alt" :title="__('Aucune localité')" :description="__('Géolocalisation indisponible.')" />
        @endforelse
    </x-ui.card>

</div>
