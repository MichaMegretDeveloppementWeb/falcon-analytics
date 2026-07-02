<div>
    <x-ui.card>
        <x-ui.section-header :title="__('Trafic')" :description="__('Sessions par jour')" class="mb-4" />
        <div wire:key="trend-{{ $period }}-{{ $subject }}">
            <x-analytics::area-chart :labels="$labels" :data="$points" :label="__('Sessions')" />
        </div>
    </x-ui.card>
</div>
