<div class="space-y-8">

    <x-ui.page-header
        :title="__('Entonnoirs')"
        :description="__('du :from au :to', [
            'from' => $range->from->isoFormat('D MMM YYYY'),
            'to' => $range->to->isoFormat('D MMM YYYY'),
        ])">
        @include('analytics::livewire.dashboard.partials.filters')
    </x-ui.page-header>

    @forelse ($reports as $report)
        @php
            $entrants = number_format($report->entrants, 0, ',', ' ');
            $entrantsLabel = $report->entrants <= 1
                ? __(':count entrant', ['count' => $entrants])
                : __(':count entrants', ['count' => $entrants]);
        @endphp
        <x-ui.card wire:key="funnel-{{ $report->key }}">
            <div class="mb-5 flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-[13px] font-semibold text-primary">{{ $report->label }}</h2>
                    <p class="mt-0.5 text-[12px] text-secondary">{{ $entrantsLabel }}</p>
                </div>
                <x-ui.badge color="indigo">{{ __('Score') }} {{ number_format($report->totalScore, 0, ',', ' ') }}</x-ui.badge>
            </div>

            <div class="space-y-3">
                @foreach ($report->steps as $index => $step)
                    @php
                        $pct = (int) round($step->conversionFromStart * 100);
                        $drop = (int) round((1 - $step->conversionFromPrevious) * 100);
                    @endphp
                    <div>
                        <div class="mb-1 flex items-center justify-between gap-3 text-[12px]">
                            <span class="font-medium text-primary">{{ $index + 1 }}. {{ $step->label }}</span>
                            <span class="flex items-center gap-2">
                                <span class="font-medium text-primary">{{ number_format($step->visitors, 0, ',', ' ') }}</span>
                                <span class="text-muted">{{ $pct }} %</span>
                            </span>
                        </div>
                        <div class="relative h-6 overflow-hidden rounded-lg bg-elevated">
                            <div class="absolute inset-y-0 left-0 rounded-lg bg-indigo-500/80" style="width: {{ max($pct, 1) }}%"></div>
                        </div>
                        <div class="mt-1 flex items-center justify-between text-[11px] text-muted">
                            <span>{{ __('Valeur :v · score :s', [
                                'v' => number_format($step->value, 0, ',', ' '),
                                's' => number_format($step->score, 0, ',', ' '),
                            ]) }}</span>
                            @if ($index > 0 && $drop > 0 && $report->steps[$index - 1]->visitors > 0)
                                <span class="text-red-500/80">-{{ $drop }} %</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    @empty
        <x-ui.empty-state
            icon="funnel"
            :title="__('Aucun entonnoir')"
            :description="__('Aucun entonnoir n\'est déclaré dans app/Analytics/funnels.php.')" />
    @endforelse

</div>
