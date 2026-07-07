<div>
    <x-ui.card>
        <x-ui.section-header :title="__('Événements et conversions au fil du temps')" class="mb-4" />
        @if (array_sum($eventsData) > 0)
            <div wire:key="ev-trend-inner-{{ $period }}-{{ $subject }}">
                <x-analytics::area-chart :labels="$labels" :data="$eventsData" :label="__('Événements')" :data2="$conversions" :label2="__('Conversions')" />
            </div>
        @else
            <div class="flex h-48 items-center justify-center rounded-lg bg-elevated text-[12px] text-muted">{{ __('Aucun événement sur la période.') }}</div>
        @endif
    </x-ui.card>
</div>
