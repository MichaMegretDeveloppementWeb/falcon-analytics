<div class="space-y-8">

    <x-ui.page-header
        :title="__('Entonnoirs')"
        :description="__('du :from au :to', [
            'from' => $range->from->isoFormat('D MMM YYYY'),
            'to' => $range->to->isoFormat('D MMM YYYY'),
        ])">
        @include('analytics::livewire.dashboard.partials.filters')
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-2">
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

                <div>
                    @foreach ($report->steps as $index => $step)
                        @php
                            $pct = (int) round($step->conversionFromStart * 100);
                            $drop = (int) round((1 - $step->conversionFromPrevious) * 100);
                            $previousVisitors = $previousReports[$report->key]->steps[$index]->visitors ?? null;
                        @endphp

                        @if ($index > 0 && $report->steps[$index - 1]->visitors > 0)
                            <div class="flex items-center justify-center gap-x-1 py-1 text-[11px] text-muted">
                                <x-ui.icon name="arrow-down" class="h-3 w-3" />
                                {{ $drop }} % {{ __('de perte') }}
                            </div>
                        @endif

                        <div class="py-1">
                            <div class="flex items-baseline justify-between gap-3">
                                <div class="flex min-w-0 items-baseline gap-x-2">
                                    <span class="text-lg font-semibold tracking-tight text-primary">{{ number_format($step->visitors, 0, ',', ' ') }}</span>
                                    <span class="truncate text-[13px] text-secondary">{{ $step->label }}</span>
                                </div>
                                <div class="flex shrink-0 items-center gap-x-2">
                                    @if ($previousVisitors !== null)
                                        @include('analytics::livewire.dashboard.partials.delta', ['current' => $step->visitors, 'previous' => $previousVisitors])
                                    @endif
                                    <span class="text-[13px] font-medium text-primary">{{ $pct }} %</span>
                                </div>
                            </div>
                            <div class="mt-2 flex justify-center">
                                <div class="h-2.5 rounded-md bg-indigo-500/80" style="width: {{ max($pct, 2) }}%"></div>
                            </div>
                            <p class="mt-1 text-center text-[11px] text-muted">{{ __('Valeur :v · score :s', [
                                'v' => number_format($step->value, 0, ',', ' '),
                                's' => number_format($step->score, 0, ',', ' '),
                            ]) }}</p>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        @empty
            <div class="lg:col-span-2">
                <x-ui.empty-state
                    icon="funnel"
                    :title="__('Aucun entonnoir')"
                    :description="__('Aucun entonnoir n\'est déclaré dans app/Analytics/funnels.php.')" />
            </div>
        @endforelse
    </div>

</div>
