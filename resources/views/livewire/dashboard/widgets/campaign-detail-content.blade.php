<div class="space-y-6">

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-analytics::kpi-card :label="__('Sessions')" :value="number_format($sessions, 0, ',', ' ')" icon="cursor-arrow-rays" :metric="$sessionsDelta">
            <div wire:key="c-spark-s-{{ $refId }}-{{ $period }}-{{ $subject }}" class="mt-3"><x-analytics::sparkline :values="$trendData" /></div>
        </x-analytics::kpi-card>
        <x-analytics::kpi-card :label="__('Visiteurs')" :value="number_format($visitors, 0, ',', ' ')" icon="users" :metric="$visitorsDelta">
            <div wire:key="c-spark-v-{{ $refId }}-{{ $period }}-{{ $subject }}" class="mt-3"><x-analytics::sparkline :values="$trendData" /></div>
        </x-analytics::kpi-card>
        <x-analytics::kpi-card :label="__('Conversions')" :value="number_format($conversions, 0, ',', ' ')" icon="check-circle" :metric="$conversionsDelta">
            <div wire:key="c-spark-conv-{{ $refId }}-{{ $period }}-{{ $subject }}" class="mt-3"><x-analytics::sparkline :values="$conversionsTrend" color="#10b981" /></div>
        </x-analytics::kpi-card>
        <x-analytics::kpi-card :label="__('Taux de conversion')" :value="$rateLabel" icon="arrow-trending-up" :metric="$rateDelta">
            <div wire:key="c-spark-rate-{{ $refId }}-{{ $period }}-{{ $subject }}" class="mt-3"><x-analytics::sparkline :values="$rateTrend" color="#10b981" /></div>
        </x-analytics::kpi-card>
    </div>

    <x-ui.card>
        <x-ui.section-header :title="__('Sessions et conversions au fil du temps')" class="mb-4" />
        @if (array_sum($trendData) > 0 || array_sum($conversionsTrend) > 0)
            <div wire:key="c-trend-{{ $refId }}-{{ $period }}-{{ $subject }}">
                <x-analytics::area-chart :labels="$trendLabels" :data="$trendData" :label="__('Sessions')" :data2="$conversionsTrend" :label2="__('Conversions')" height="h-56" />
            </div>
        @else
            <div class="flex h-56 items-center justify-center rounded-lg bg-elevated text-[12px] text-muted">{{ __('Aucune session sur la période.') }}</div>
        @endif
    </x-ui.card>

    @include('analytics::livewire.dashboard.partials.marketing-conversions', ['showAd' => true])

</div>
