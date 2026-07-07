@php
    $routeName = config('analytics.marketing.route_name', 'marketing');
    $maxAd = collect($adRows)->max('sessions') ?: 1;
@endphp

<div class="space-y-8">

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
        <x-analytics::kpi-card :label="__('Conversions')" :value="number_format($conversions, 0, ',', ' ')" icon="check-circle" :metric="$conversionsDelta">
            <div wire:key="spark-conv-{{ $range->days }}-{{ $subject }}" class="mt-3"><x-analytics::sparkline :values="$conversionsTrend" color="#10b981" /></div>
        </x-analytics::kpi-card>
        <x-analytics::kpi-card :label="__('Taux de conversion')" :value="$rateLabel" icon="arrow-trending-up" :metric="$rateDelta">
            <div wire:key="spark-rate-{{ $range->days }}-{{ $subject }}" class="mt-3"><x-analytics::sparkline :values="$rateTrend" color="#10b981" /></div>
        </x-analytics::kpi-card>
    </div>

    {{-- Trend --}}
    <x-ui.card>
        <x-ui.section-header :title="__('Sessions et conversions au fil du temps')" class="mb-4" />
        @if (array_sum($trendData) > 0 || array_sum($conversionsTrend) > 0)
            <div wire:key="mkt-trend-{{ $period }}-{{ $subject }}">
                <x-analytics::area-chart :labels="$trendLabels" :data="$trendData" :label="__('Sessions')" :data2="$conversionsTrend" :label2="__('Conversions')" />
            </div>
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
                        <x-ui.table.header-cell align="right">{{ __('Visiteurs') }}</x-ui.table.header-cell>
                        <x-ui.table.header-cell align="right">{{ __('Conv.') }}</x-ui.table.header-cell>
                        <x-ui.table.header-cell :last="true" align="right">{{ __('Taux') }}</x-ui.table.header-cell>
                    </x-ui.table.head>
                    <x-ui.table.body>
                        @foreach ($campaignRows as $row)
                            @php $showUrl = route($routeName.'.campaigns.show', $row['id']); @endphp
                            <x-ui.table.row wire:key="perf-{{ $row['id'] }}" onclick="window.location='{{ $showUrl }}'" class="cursor-pointer">
                                <x-ui.table.cell :first="true" variant="primary">
                                    <a href="{{ $showUrl }}" class="cursor-pointer text-[13px] font-medium text-primary hover:underline">{{ $row['name'] }}</a>
                                </x-ui.table.cell>
                                <x-ui.table.cell align="right" class="tabular-nums">{{ number_format($row['sessions'], 0, ',', ' ') }}</x-ui.table.cell>
                                <x-ui.table.cell align="right" class="tabular-nums">{{ number_format($row['visitors'], 0, ',', ' ') }}</x-ui.table.cell>
                                <x-ui.table.cell align="right" class="font-medium tabular-nums text-primary">{{ number_format($row['conversions'], 0, ',', ' ') }}</x-ui.table.cell>
                                <x-ui.table.cell :last="true" align="right" class="tabular-nums text-secondary">{{ number_format($row['rate'], 1, ',', ' ')."\u{00A0}%" }}</x-ui.table.cell>
                            </x-ui.table.row>
                        @endforeach
                    </x-ui.table.body>
                </x-ui.table>
            @endif
        </div>

        <div class="lg:col-span-5">
            <div class="mb-4 flex items-center justify-between">
                <x-ui.section-header :title="__('Top pubs')" :description="__('conversions / sessions')" />
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
                        <span class="shrink-0 text-right text-[12px] tabular-nums"><span class="font-medium text-primary">{{ number_format($row['conversions'], 0, ',', ' ') }}</span><span class="text-muted"> / {{ number_format($row['sessions'], 0, ',', ' ') }}</span></span>
                    </a>
                @empty
                    <div class="px-2 py-6 text-center text-[12px] text-muted">{{ __('Aucune pub avec du trafic sur la période.') }}</div>
                @endforelse
            </x-ui.card>
        </div>
    </div>

</div>
