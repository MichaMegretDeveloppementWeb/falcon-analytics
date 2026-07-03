@php
    use Illuminate\Support\Str;

    $subjectResolver = app(\Falcon\Analytics\Services\SubjectResolver::class);
@endphp

<div class="space-y-6">

    @include('analytics::livewire.dashboard.partials.tooltip-host')

    <x-ui.page-header
        :title="__('Visiteurs')"
        :description="__('du :from au :to', [
            'from' => $range->from->isoFormat('D MMM YYYY'),
            'to' => $range->to->isoFormat('D MMM YYYY'),
        ])">
        @include('analytics::livewire.dashboard.partials.filters')
    </x-ui.page-header>

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

    <x-ui.search-input wire:model.live.debounce.300ms="search" :placeholder="__('Rechercher un nom, un ID…')" class="w-full sm:max-w-xs" />

    @if ($visitors->isEmpty())
        <x-ui.empty-state
            icon="users"
            :title="__('Aucun visiteur')"
            :description="__('Aucun visiteur ne correspond aux filtres.')" />
    @else
        <x-ui.table>
            <x-ui.table.head>
                <x-ui.table.header-cell :first="true">{{ __('Visiteur') }}</x-ui.table.header-cell>
                <x-ui.table.header-cell>{{ __('Type') }}</x-ui.table.header-cell>
                <x-ui.table.header-cell>@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'period_sessions', 'label' => __('Sessions')])</x-ui.table.header-cell>
                <x-ui.table.header-cell>@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'first_seen_at', 'label' => __('Première visite')])</x-ui.table.header-cell>
                <x-ui.table.header-cell>@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'last_seen_at', 'label' => __('Dernière visite')])</x-ui.table.header-cell>
                <x-ui.table.header-cell>{{ __('Localité') }}</x-ui.table.header-cell>
                <x-ui.table.header-cell :last="true">{{ __('Acquisition') }}</x-ui.table.header-cell>
            </x-ui.table.head>
            <x-ui.table.body>
                @foreach ($visitors as $visitor)
                    @php
                        $subjectName = $visitor->subject_type ? ($subjectNames[$visitor->subject_type.':'.$visitor->subject_id] ?? null) : null;
                        $subjectLabel = $visitor->subject_type ? $subjectResolver->label($visitor->subject_type) : null;
                    @endphp
                    <x-ui.table.row wire:key="visitor-{{ $visitor->id }}">
                        <x-ui.table.cell :first="true">
                            <div class="flex flex-col gap-0.5">
                                <span class="text-[13px] font-medium text-primary">
                                    @if ($visitor->subject_type)
                                        {{ $subjectName ?? $subjectLabel.' #'.$visitor->subject_id }}
                                    @else
                                        {{ __('Visiteur #:id', ['id' => $visitor->id]) }}
                                    @endif
                                </span>
                                <span class="text-[11px] text-muted">{{ Str::limit($visitor->uuid, 16, '') }}</span>
                            </div>
                        </x-ui.table.cell>
                        <x-ui.table.cell>
                            @if ($visitor->subject_type)
                                <x-ui.badge color="blue">{{ $subjectLabel }}</x-ui.badge>
                            @else
                                <x-ui.badge color="gray">{{ __('Anonyme') }}</x-ui.badge>
                            @endif
                        </x-ui.table.cell>
                        <x-ui.table.cell class="tabular-nums">{{ number_format((int) $visitor->period_sessions, 0, ',', ' ') }}</x-ui.table.cell>
                        <x-ui.table.cell class="whitespace-nowrap">{{ $visitor->first_seen_at->translatedFormat('d M Y') }}</x-ui.table.cell>
                        <x-ui.table.cell class="whitespace-nowrap text-secondary">{{ $visitor->last_seen_at->diffForHumans() }}</x-ui.table.cell>
                        <x-ui.table.cell class="whitespace-nowrap">
                            @if ($visitor->last_country || $visitor->last_city)
                                <x-analytics::country :code="$visitor->last_country" :city="$visitor->last_city" />
                            @else
                                <span class="text-muted">{{ __('Inconnu') }}</span>
                            @endif
                        </x-ui.table.cell>
                        <x-ui.table.cell :last="true" class="whitespace-nowrap">
                            @if ($visitor->acquisition_source)
                                <x-ui.badge color="gray"><x-analytics::source :value="$visitor->acquisition_source" /></x-ui.badge>
                            @else
                                <span class="text-muted">{{ __('Directe') }}</span>
                            @endif
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @endforeach
            </x-ui.table.body>
        </x-ui.table>

        @if ($visitors->hasPages())
            <div class="mt-6"><x-ui.pagination :paginator="$visitors" mode="livewire" /></div>
        @endif
    @endif

</div>
