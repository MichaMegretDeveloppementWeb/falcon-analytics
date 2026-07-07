<div>
    <x-ui.card>
        <x-ui.section-header :title="__('Trafic et conversions')" :description="__('Sessions et conversions par jour')" class="mb-4" />
        <div wire:key="trend-{{ $period }}-{{ $subject }}">
            <x-analytics::area-chart :labels="$labels" :data="$points" :label="__('Sessions')" :data2="$conversions" :label2="__('Conversions')" />
        </div>
    </x-ui.card>
</div>
