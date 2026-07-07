<div>
    <x-ui.card>
        <x-ui.section-header :title="__('Événements et conversions au fil du temps')" class="mb-4" />
        @if (array_sum($eventsData) > 0)
            <div wire:key="ev-trend-inner-{{ $period }}-{{ $subject }}" class="space-y-5">
                <div class="grid grid-cols-2 gap-4">
                    <div class="rounded-lg bg-elevated px-3 py-2">
                        <p class="flex items-center gap-1.5 text-[11px] font-medium text-secondary"><span class="h-1.5 w-1.5 rounded-full bg-[#1684ea]"></span>{{ __('Événements') }}</p>
                        <div class="mt-1"><x-analytics::sparkline :values="$eventsData" /></div>
                    </div>
                    <div class="rounded-lg bg-elevated px-3 py-2">
                        <p class="flex items-center gap-1.5 text-[11px] font-medium text-secondary"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>{{ __('Conversions') }}</p>
                        <div class="mt-1"><x-analytics::sparkline :values="$conversions" color="#10b981" /></div>
                    </div>
                </div>
                <x-analytics::area-chart :labels="$labels" :data="$eventsData" :label="__('Événements')" :data2="$conversions" :label2="__('Conversions')" />
            </div>
        @else
            <div class="flex h-48 items-center justify-center rounded-lg bg-elevated text-[12px] text-muted">{{ __('Aucun événement sur la période.') }}</div>
        @endif
    </x-ui.card>
</div>
