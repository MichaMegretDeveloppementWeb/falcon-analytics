@php
    use Illuminate\Support\Str;

    $labels = array_map(fn ($point) => $point->date->isoFormat('D MMM'), $trend);
    $sessionsData = array_map(fn ($point) => $point->sessions, $trend);
    $pageviewsData = array_map(fn ($point) => $point->pageviews, $trend);

    $maxPages = max(array_column($topPages, 'total') ?: [0]);
    $maxSources = max(array_column($topSources, 'total') ?: [0]);
@endphp

<div class="space-y-8">

    <x-ui.page-header
        :title="__('Vue d\'ensemble')"
        :description="__('du :from au :to', [
            'from' => $range->from->isoFormat('D MMM YYYY'),
            'to' => $range->to->isoFormat('D MMM YYYY'),
        ])">
        @include('analytics::livewire.dashboard.partials.filters')
    </x-ui.page-header>

    {{-- Headline counters --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-ui.stat-card :label="__('Visiteurs')" :value="number_format($metrics->visitors, 0, ',', ' ')" icon="users" />
        <x-ui.stat-card :label="__('Sessions')" :value="number_format($metrics->sessions, 0, ',', ' ')" icon="cursor-arrow-rays" />
        <x-ui.stat-card :label="__('Pages vues')" :value="number_format($metrics->pageviews, 0, ',', ' ')" icon="eye" />
        <x-ui.stat-card :label="__('Pages / session')" :value="number_format($metrics->pagesPerSession(), 1, ',', ' ')" icon="rectangle-stack" />
    </div>

    {{-- Daily trend --}}
    <x-ui.card>
        <x-ui.section-header :title="__('Trafic')" :description="__('Sessions et pages vues par jour')" class="mb-4" />
        <x-ui.chart
            type="line"
            wire:key="trend-{{ $period }}-{{ $subject }}"
            :labels="$labels"
            :datasets="[
                [
                    'label' => __('Sessions'),
                    'data' => $sessionsData,
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.08)',
                    'fill' => true,
                    'tension' => 0.35,
                    'borderWidth' => 2,
                    'pointRadius' => 0,
                ],
                [
                    'label' => __('Pages vues'),
                    'data' => $pageviewsData,
                    'borderColor' => '#9ca3af',
                    'backgroundColor' => 'transparent',
                    'fill' => false,
                    'tension' => 0.35,
                    'borderWidth' => 2,
                    'pointRadius' => 0,
                    'borderDash' => [4, 4],
                ],
            ]" />
    </x-ui.card>

    {{-- Top entry pages + sources --}}
    <div class="grid gap-6 lg:grid-cols-2">

        <x-ui.card>
            <x-ui.section-header :title="__('Pages d\'entrée')" :description="__('Où commencent les sessions')" class="mb-4" />
            @forelse ($topPages as $page)
                @php $pct = $maxPages > 0 ? round($page['total'] / $maxPages * 100) : 0; @endphp
                <div class="flex items-center gap-3 py-1.5">
                    <span class="w-44 shrink-0 truncate text-[13px] text-primary" title="{{ $page['route'] }}">{{ $page['route'] }}</span>
                    <div class="relative h-1.5 flex-1 overflow-hidden rounded-full bg-elevated">
                        <div class="absolute inset-y-0 left-0 rounded-full bg-indigo-500/70" style="width: {{ $pct }}%"></div>
                    </div>
                    <span class="w-12 shrink-0 text-right text-[12px] font-medium text-secondary">{{ number_format($page['total'], 0, ',', ' ') }}</span>
                </div>
            @empty
                <x-ui.empty-state icon="document" :title="__('Aucune page')" :description="__('Aucune session sur la période.')" />
            @endforelse
        </x-ui.card>

        <x-ui.card>
            <x-ui.section-header :title="__('Sources')" :description="__('Origine de l\'acquisition')" class="mb-4" />
            @forelse ($topSources as $source)
                @php $pct = $maxSources > 0 ? round($source['total'] / $maxSources * 100) : 0; @endphp
                <div class="flex items-center gap-3 py-1.5">
                    <span class="w-44 shrink-0 truncate text-[13px] text-primary">{{ Str::headline($source['source']) }}</span>
                    <div class="relative h-1.5 flex-1 overflow-hidden rounded-full bg-elevated">
                        <div class="absolute inset-y-0 left-0 rounded-full bg-emerald-500/70" style="width: {{ $pct }}%"></div>
                    </div>
                    <span class="w-12 shrink-0 text-right text-[12px] font-medium text-secondary">{{ number_format($source['total'], 0, ',', ' ') }}</span>
                </div>
            @empty
                <x-ui.empty-state icon="signal" :title="__('Aucune source')" :description="__('Aucune session sur la période.')" />
            @endforelse
        </x-ui.card>

    </div>

</div>
