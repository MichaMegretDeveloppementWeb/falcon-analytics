<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    @forelse ($reports as $report)
        @php
            $lastStep = $report->steps === [] ? null : $report->steps[array_key_last($report->steps)];
            $overallPct = $lastStep !== null ? (int) round($lastStep->conversionFromStart * 100) : 0;
            $entrants = number_format($report->entrants, 0, ',', ' ');
        @endphp
        <x-ui.card wire:key="funnel-{{ $report->key }}" class="flex flex-col">
            {{-- Header: name, entrants → completion, weighted score. --}}
            <div class="mb-5 flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="text-[13px] font-semibold text-primary">{{ $report->label }}</h2>
                    <p class="mt-0.5 text-[12px] text-secondary">
                        {{ $report->entrants <= 1 ? __(':count entrant', ['count' => $entrants]) : __(':count entrants', ['count' => $entrants]) }}
                        @if ($report->entrants > 0 && $lastStep !== null)
                            <span class="text-muted">·</span>
                            {{ $overallPct."\u{00A0}%" }} {{ __('de conversion') }}
                        @endif
                    </p>
                </div>
                <x-ui.badge color="gray" class="shrink-0">{{ __('Score') }} {{ number_format($report->totalScore, 0, ',', ' ') }}</x-ui.badge>
            </div>

            @if ($report->entrants === 0)
                <div class="flex flex-1 items-center justify-center rounded-lg bg-elevated px-4 py-10 text-center">
                    <p class="max-w-xs text-[12px] text-muted">{{ __('Aucun visiteur n\'est encore entré dans ce tunnel sur la période.') }}</p>
                </div>
            @else
                <div class="space-y-1.5">
                    @foreach ($report->steps as $index => $step)
                        @php
                            $pct = (int) round($step->conversionFromStart * 100);
                            $previous = $index > 0 ? $report->steps[$index - 1] : null;
                            $lost = $previous !== null ? max(0, $previous->visitors - $step->visitors) : 0;
                            $drop = $previous !== null && $previous->visitors > 0 ? (int) round((1 - $step->conversionFromPrevious) * 100) : 0;
                            $previousVisitors = $previousReports[$report->key]->steps[$index]->visitors ?? null;
                        @endphp

                        {{-- Loss between steps. --}}
                        @if ($previous !== null && $previous->visitors > 0 && $lost > 0)
                            <div class="flex items-center gap-1.5 pl-0.5 text-[11px] text-muted">
                                <x-ui.icon name="arrow-trending-down" class="h-3.5 w-3.5" />
                                <span>&minus;{{ $drop."\u{00A0}%" }}</span>
                                <span class="text-muted/60">·</span>
                                <span>{{ $lost <= 1 ? __(':count perdu', ['count' => $lost]) : __(':count perdus', ['count' => number_format($lost, 0, ',', ' ')]) }}</span>
                            </div>
                        @endif

                        {{-- Step: count + label, conversion, proportional (narrowing) bar. --}}
                        <div>
                            <div class="flex items-baseline justify-between gap-3">
                                <div class="flex min-w-0 items-baseline gap-x-2">
                                    <span class="text-lg font-semibold tabular-nums tracking-tight text-primary">{{ number_format($step->visitors, 0, ',', ' ') }}</span>
                                    <span class="truncate text-[13px] text-secondary" data-tooltip="{{ $step->label }}">{{ $step->label }}</span>
                                </div>
                                <div class="flex shrink-0 items-center gap-x-2">
                                    @if ($previousVisitors !== null && $previousVisitors > 0)
                                        @include('analytics::livewire.dashboard.partials.delta', ['current' => $step->visitors, 'previous' => $previousVisitors])
                                    @endif
                                    <span class="text-[13px] font-semibold tabular-nums text-primary">{{ $pct."\u{00A0}%" }}</span>
                                </div>
                            </div>
                            <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-elevated">
                                <div class="h-full rounded-full bg-[#1684ea]" style="width: {{ max($pct, 2) }}%"></div>
                            </div>
                            <p class="mt-1 text-[11px] text-muted">{{ __('Valeur :v · score :s', [
                                'v' => number_format($step->value, 0, ',', ' '),
                                's' => number_format($step->score, 0, ',', ' '),
                            ]) }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>
    @empty
        <div class="lg:col-span-2">
            <x-ui.empty-state
                icon="funnel"
                :title="__('Aucun tunnel')"
                :description="__('Aucun tunnel n\'est déclaré dans app/Analytics/funnels.php.')" />
        </div>
    @endforelse
</div>
