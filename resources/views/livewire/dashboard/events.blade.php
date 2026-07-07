<div class="space-y-8">

    <x-ui.page-header
        :title="__('Événements & conversions')"
        :description="__('du :from au :to', [
            'from' => $range->from->isoFormat('D MMM YYYY'),
            'to' => $range->to->isoFormat('D MMM YYYY'),
        ])">
        @include('analytics::livewire.dashboard.partials.filters')
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-analytics::kpi-card :label="__('Événements')" :value="number_format($events, 0, ',', ' ')" icon="bolt" :metric="$eventsDelta">
            <div wire:key="ev-spark-e-{{ $range->days }}-{{ $subject }}" class="mt-3"><x-analytics::sparkline :values="$eventsTrend" /></div>
        </x-analytics::kpi-card>
        <x-analytics::kpi-card :label="__('Conversions')" :value="number_format($conversions, 0, ',', ' ')" icon="check-circle" :metric="$conversionsDelta">
            <div wire:key="ev-spark-c-{{ $range->days }}-{{ $subject }}" class="mt-3"><x-analytics::sparkline :values="$conversionsTrend" color="#10b981" /></div>
        </x-analytics::kpi-card>
        <x-analytics::kpi-card :label="__('Valeur des conversions')" :value="number_format($value, 0, ',', ' ').' pts'" icon="sparkles" :metric="$valueDelta" :description="__('somme des valeurs des conversions')" />
    </div>

    <livewire:analytics-events-trend-chart :period="$period" :subject="$subject" :key="'ev-trend-'.$period.'-'.$subject" />


    <div>
        <x-ui.section-header :title="__('Détail des événements')" :description="__('Chaque événement du site et le nombre de fois qu\'il s\'est produit — du plus au moins fréquent.')" class="mb-4" />
        @if ($breakdown === [])
            <x-ui.empty-state icon="bolt" :title="__('Aucun événement sur la période')" :description="__('Les événements déclarés apparaîtront ici dès qu\'ils se produiront sur le site.')" />
        @else
            <x-ui.table>
                <x-ui.table.head>
                    <x-ui.table.header-cell :first="true">{{ __('Événement') }}</x-ui.table.header-cell>
                    <x-ui.table.header-cell>{{ __('Type') }}</x-ui.table.header-cell>
                    <x-ui.table.header-cell align="right">{{ __('Occurrences') }}</x-ui.table.header-cell>
                    <x-ui.table.header-cell align="right">{{ __('Visiteurs') }}</x-ui.table.header-cell>
                    <x-ui.table.header-cell align="right">{{ __('Valeur unit.') }}</x-ui.table.header-cell>
                    <x-ui.table.header-cell :last="true" align="right">{{ __('Valeur totale') }}</x-ui.table.header-cell>
                </x-ui.table.head>
                <x-ui.table.body>
                    @foreach ($breakdown as $row)
                        <x-ui.table.row wire:key="ev-{{ $loop->index }}">
                            <x-ui.table.cell :first="true" variant="primary">
                                <div class="flex flex-col">
                                    <span class="text-[13px] font-medium text-primary">{{ $row['label'] }}</span>
                                    @if ($row['label'] !== $row['name'])<span class="text-[11px] text-muted">{{ $row['name'] }}</span>@endif
                                </div>
                            </x-ui.table.cell>
                            <x-ui.table.cell>
                                @if ($row['isConversion'])
                                    <x-ui.badge color="emerald"><x-ui.icon name="check-circle" class="h-3 w-3" /> {{ __('Conversion') }}</x-ui.badge>
                                @else
                                    <x-ui.badge color="gray">{{ __('Événement') }}</x-ui.badge>
                                @endif
                            </x-ui.table.cell>
                            <x-ui.table.cell align="right" class="font-medium tabular-nums text-primary">{{ number_format($row['count'], 0, ',', ' ') }}</x-ui.table.cell>
                            <x-ui.table.cell align="right" class="tabular-nums">{{ number_format($row['visitors'], 0, ',', ' ') }}</x-ui.table.cell>
                            <x-ui.table.cell align="right" class="tabular-nums text-secondary">{{ $row['value'] !== null ? number_format($row['value'], 0, ',', ' ')."\u{00A0}pts" : '—' }}</x-ui.table.cell>
                            <x-ui.table.cell :last="true" align="right" class="tabular-nums">{{ $row['value'] !== null ? number_format($row['valueTotal'], 0, ',', ' ')."\u{00A0}pts" : '—' }}</x-ui.table.cell>
                        </x-ui.table.row>
                    @endforeach
                </x-ui.table.body>
            </x-ui.table>
        @endif
    </div>
</div>
