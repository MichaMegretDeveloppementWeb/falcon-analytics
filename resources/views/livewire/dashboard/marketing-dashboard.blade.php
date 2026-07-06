@php
    $routeName = config('analytics.marketing.route_name', 'marketing');
    $maxCampaign = collect($campaignRows)->max('sessions') ?: 1;
    $maxAd = collect($adRows)->max('sessions') ?: 1;
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

    {{-- KPIs with integrated sparklines --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-analytics::kpi-card :label="__('Sessions issues de pubs')" :value="number_format($sessions, 0, ',', ' ')" icon="cursor-arrow-rays" :metric="$sessionsDelta">
            <div wire:key="spark-sessions-{{ $range->days }}-{{ $subject }}" class="mt-3">
                <x-analytics::sparkline :values="$trendData" />
            </div>
        </x-analytics::kpi-card>
        <x-analytics::kpi-card :label="__('Visiteurs issus de pubs')" :value="number_format($visitors, 0, ',', ' ')" icon="users" :metric="$visitorsDelta">
            <div wire:key="spark-visitors-{{ $range->days }}-{{ $subject }}" class="mt-3">
                <x-analytics::sparkline :values="$trendData" />
            </div>
        </x-analytics::kpi-card>
        <x-analytics::kpi-card :label="__('Campagnes')" :value="number_format($campaignCount, 0, ',', ' ')" icon="megaphone" />
        <x-analytics::kpi-card :label="__('Pubs')" :value="number_format($adCount, 0, ',', ' ')" icon="rectangle-stack" />
    </div>

    {{-- Trend --}}
    <x-ui.card>
        <x-ui.section-header :title="__('Sessions issues de pubs au fil du temps')" class="mb-4" />
        @if (array_sum($trendData) > 0)
            <x-analytics::area-chart :labels="$trendLabels" :data="$trendData" :label="__('Sessions')" />
        @else
            <div class="flex h-48 items-center justify-center rounded-lg bg-elevated text-[12px] text-muted">{{ __('Aucune session issue de pubs sur la période.') }}</div>
        @endif
    </x-ui.card>

    {{-- Campaign performance + top ads --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        <div class="lg:col-span-7">
            <div class="mb-4 flex items-center justify-between">
                <x-ui.section-header :title="__('Performance des campagnes')" />
                <a href="{{ route($routeName.'.campaigns') }}" class="inline-flex cursor-pointer items-center gap-x-1 text-[12px] font-medium text-secondary transition-colors hover:text-primary">{{ __('Toutes les campagnes') }} <x-ui.icon name="arrow-right" class="h-3.5 w-3.5" /></a>
            </div>
            @if ($campaignRows === [])
                <x-ui.empty-state icon="megaphone" :title="__('Aucune campagne active sur la période')" :description="__('Le trafic taggé sera attribué ici dès qu\'une campagne correspondra.')" />
            @else
                <x-ui.table>
                    <x-ui.table.head>
                        <x-ui.table.header-cell :first="true">{{ __('Campagne') }}</x-ui.table.header-cell>
                        <x-ui.table.header-cell align="right">{{ __('Sessions') }}</x-ui.table.header-cell>
                        <x-ui.table.header-cell :last="true" align="right">{{ __('Visiteurs') }}</x-ui.table.header-cell>
                    </x-ui.table.head>
                    <x-ui.table.body>
                        @foreach ($campaignRows as $row)
                            @php $showUrl = route($routeName.'.campaigns.show', $row['id']); @endphp
                            <x-ui.table.row wire:key="perf-{{ $row['id'] }}" onclick="window.location='{{ $showUrl }}'" class="cursor-pointer">
                                <x-ui.table.cell :first="true" variant="primary">
                                    <a href="{{ $showUrl }}" class="cursor-pointer text-[13px] font-medium text-primary hover:underline">{{ $row['name'] }}</a>
                                </x-ui.table.cell>
                                <x-ui.table.cell align="right" class="tabular-nums">{{ number_format($row['sessions'], 0, ',', ' ') }}</x-ui.table.cell>
                                <x-ui.table.cell :last="true" align="right" class="tabular-nums">{{ number_format($row['visitors'], 0, ',', ' ') }}</x-ui.table.cell>
                            </x-ui.table.row>
                        @endforeach
                    </x-ui.table.body>
                </x-ui.table>
            @endif
        </div>

        <div class="lg:col-span-5">
            <div class="mb-4 flex items-center justify-between">
                <x-ui.section-header :title="__('Top pubs')" />
                <a href="{{ route($routeName.'.ads') }}" class="inline-flex cursor-pointer items-center gap-x-1 text-[12px] font-medium text-secondary transition-colors hover:text-primary">{{ __('Toutes les pubs') }} <x-ui.icon name="arrow-right" class="h-3.5 w-3.5" /></a>
            </div>
            <x-ui.card>
                @forelse ($adRows as $row)
                    <a href="{{ $row['campaign_id'] ? route($routeName.'.campaigns.show', $row['campaign_id']) : '#' }}" class="flex items-center gap-3 py-1.5 {{ $row['campaign_id'] ? 'cursor-pointer' : '' }}" wire:key="topad-{{ $row['id'] }}">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-elevated">
                            <x-ui.icon name="rectangle-stack" class="h-3.5 w-3.5 text-secondary" />
                        </span>
                        <span class="w-32 shrink-0 truncate text-[13px] text-primary">{{ $row['name'] }}</span>
                        <div class="relative h-1.5 flex-1 overflow-hidden rounded-full bg-elevated">
                            <div class="absolute inset-y-0 left-0 rounded-full bg-[#1684ea]/70" style="width: {{ max((int) round($row['sessions'] / $maxAd * 100), 3) }}%"></div>
                        </div>
                        <span class="w-10 shrink-0 text-right text-[12px] font-medium text-secondary tabular-nums">{{ number_format($row['sessions'], 0, ',', ' ') }}</span>
                    </a>
                @empty
                    <div class="px-2 py-6 text-center text-[12px] text-muted">{{ __('Aucune pub avec du trafic sur la période.') }}</div>
                @endforelse
            </x-ui.card>
        </div>
    </div>

</div>
