<div>
    <x-ui.card>
        <x-ui.section-header :title="__('Sessions et conversions au fil du temps')" class="mb-4" />
        @if (array_sum($sessionsData) > 0 || array_sum($conversionsData) > 0)
            <div wire:key="mkt-trend-inner-{{ $scope }}-{{ $refId }}-{{ $period }}-{{ $subject }}">
                <x-analytics::area-chart :labels="$labels" :data="$sessionsData" :label="__('Sessions')" :data2="$conversionsData" :label2="__('Conversions')" />
            </div>
        @else
            <div class="flex h-48 items-center justify-center rounded-lg bg-elevated text-[12px] text-muted">{{ __('Aucune donnée sur la période.') }}</div>
        @endif
    </x-ui.card>
</div>
