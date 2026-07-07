<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <x-analytics::kpi-card :label="__('Visiteurs')" :value="number_format($metrics->visitors->delta->current, 0, ',', ' ')" icon="users"
        :metric="$metrics->visitors->delta">
        <div wire:key="spark-v-visitors-{{ $period }}-{{ $subject }}" class="mt-3"><x-analytics::sparkline :values="$metrics->visitors->sparkline" /></div>
    </x-analytics::kpi-card>
    <x-analytics::kpi-card :label="__('Nouveaux')" :value="number_format($metrics->newVisitors->delta->current, 0, ',', ' ')" icon="sparkles"
        :metric="$metrics->newVisitors->delta">
        <div wire:key="spark-v-new-{{ $period }}-{{ $subject }}" class="mt-3"><x-analytics::sparkline :values="$metrics->newVisitors->sparkline" /></div>
    </x-analytics::kpi-card>
    <x-analytics::kpi-card :label="__('Récurrents')" :value="number_format($metrics->returning->delta->current, 0, ',', ' ')" icon="arrow-path"
        :metric="$metrics->returning->delta">
        <div wire:key="spark-v-returning-{{ $period }}-{{ $subject }}" class="mt-3"><x-analytics::sparkline :values="$metrics->returning->sparkline" /></div>
    </x-analytics::kpi-card>
    <x-analytics::kpi-card :label="__('Sessions / visiteur')" :value="number_format($metrics->sessionsPerVisitor->delta->current, 1, ',', ' ')" icon="cursor-arrow-rays"
        :metric="$metrics->sessionsPerVisitor->delta">
        <div wire:key="spark-v-spv-{{ $period }}-{{ $subject }}" class="mt-3"><x-analytics::sparkline :values="$metrics->sessionsPerVisitor->sparkline" /></div>
    </x-analytics::kpi-card>
</div>
