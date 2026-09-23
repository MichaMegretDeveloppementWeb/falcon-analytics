@php use Falcon\Analytics\Support\NumberLabel; @endphp

<x-analytics::root area="admin" class="an:grid an:grid-cols-2 an:gap-4 an:lg:grid-cols-4">
    <x-analytics::kpi-card :label="__('Visiteurs')" :value="NumberLabel::for($metrics->visitors->delta->current)" icon="users"
        :metric="$metrics->visitors->delta">
        <div wire:key="spark-v-visitors-{{ $period }}-{{ $subject }}" class="an:mt-3"><x-analytics::sparkline :values="$metrics->visitors->sparkline" /></div>
    </x-analytics::kpi-card>
    <x-analytics::kpi-card :label="__('Nouveaux')" :value="NumberLabel::for($metrics->newVisitors->delta->current)" icon="sparkles"
        :metric="$metrics->newVisitors->delta">
        <div wire:key="spark-v-new-{{ $period }}-{{ $subject }}" class="an:mt-3"><x-analytics::sparkline :values="$metrics->newVisitors->sparkline" /></div>
    </x-analytics::kpi-card>
    <x-analytics::kpi-card :label="__('Récurrents')" :value="NumberLabel::for($metrics->returning->delta->current)" icon="arrow-path"
        :metric="$metrics->returning->delta">
        <div wire:key="spark-v-returning-{{ $period }}-{{ $subject }}" class="an:mt-3"><x-analytics::sparkline :values="$metrics->returning->sparkline" /></div>
    </x-analytics::kpi-card>
    <x-analytics::kpi-card :label="__('Sessions par visiteur')" :value="NumberLabel::for($metrics->sessionsPerVisitor->delta->current, 1)" icon="cursor-arrow-rays"
        :metric="$metrics->sessionsPerVisitor->delta">
        <div wire:key="spark-v-spv-{{ $period }}-{{ $subject }}" class="an:mt-3"><x-analytics::sparkline :values="$metrics->sessionsPerVisitor->sparkline" /></div>
    </x-analytics::kpi-card>
</x-analytics::root>
