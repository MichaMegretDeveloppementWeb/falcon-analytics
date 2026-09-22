<x-analytics::root area="admin" class="an:grid an:grid-cols-1 an:gap-6 an:lg:grid-cols-2">
    @forelse ($reports as $report)
        @php
            $lastStep = $report->steps === [] ? null : $report->steps[array_key_last($report->steps)];
            $overallPct = $lastStep !== null ? (int) round($lastStep->conversionFromStart * 100) : 0;
            $entrants = number_format($report->entrants, 0, ',', ' ');
        @endphp
        <x-ui::card wire:key="funnel-{{ $report->key }}" class="an:flex an:flex-col">
            {{-- Header --}}
            <div class="an:mb-5 an:flex an:items-start an:justify-between an:gap-4">
                <div class="an:min-w-0">
                    <h2 class="an:text-[13px] an:font-semibold an:text-primary">{{ $report->label }}</h2>
                    <p class="an:mt-0.5 an:text-[12px] an:text-secondary">
                        {{ $report->entrants <= 1 ? __(':count entrant', ['count' => $entrants]) : __(':count entrants', ['count' => $entrants]) }}
                        @if ($report->entrants > 0 && $lastStep !== null)
                            <span class="an:text-muted">·</span>
                            {{ $overallPct."\u{00A0}%" }} {{ __('de conversion') }}
                        @endif
                    </p>
                </div>
                <x-ui::badge color="gray" class="an:shrink-0">{{ __('Score') }} {{ number_format($report->totalScore, 0, ',', ' ')."\u{00A0}pts" }}</x-ui::badge>
            </div>

            @if ($report->entrants === 0)
                <div class="an:flex an:flex-1 an:items-center an:justify-center an:rounded-lg an:bg-elevated an:px-4 an:py-10 an:text-center">
                    <p class="an:max-w-xs an:text-[12px] an:text-muted">{{ __('Aucun visiteur n\'est encore entré dans ce tunnel sur la période.') }}</p>
                </div>
            @else
                <div class="an:space-y-1.5">
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
                            <div class="an:flex an:items-center an:gap-1.5 an:pl-0.5 an:text-[11px] an:text-muted">
                                <x-ui::icon name="arrow-trending-down" class="an:h-3.5 an:w-3.5" />
                                <span>&minus;{{ $drop."\u{00A0}%" }}</span>
                                <span class="an:text-muted/60">·</span>
                                <span>{{ $lost <= 1 ? __(':count perdu', ['count' => $lost]) : __(':count perdus', ['count' => number_format($lost, 0, ',', ' ')]) }}</span>
                            </div>
                        @endif

                        {{-- Step --}}
                        <div>
                            <div class="an:flex an:items-baseline an:justify-between an:gap-3">
                                <div class="an:flex an:min-w-0 an:items-baseline an:gap-x-2">
                                    <span class="an:text-lg an:font-semibold an:tabular-nums an:tracking-tight an:text-primary">{{ number_format($step->visitors, 0, ',', ' ') }}</span>
                                    <span class="an:truncate an:text-[13px] an:text-secondary" data-an-tooltip="{{ $step->label }}">{{ $step->label }}</span>
                                </div>
                                <div class="an:flex an:shrink-0 an:items-center an:gap-x-2">
                                    @if ($previousVisitors !== null && $previousVisitors > 0)
                                        @include('analytics::livewire.dashboard.partials.delta', ['current' => $step->visitors, 'previous' => $previousVisitors])
                                    @endif
                                    <span class="an:text-[13px] an:font-semibold an:tabular-nums an:text-primary">{{ $pct."\u{00A0}%" }}</span>
                                </div>
                            </div>
                            <div class="an:mt-1.5 an:h-2 an:w-full an:overflow-hidden an:rounded-full an:bg-elevated">
                                <div class="an:h-full an:rounded-full an:bg-series-1" style="width: {{ max($pct, 2) }}%"></div>
                            </div>
                            {{-- Parallel branches: which way in visitors actually took. --}}
                            @if ($step->branches !== [])
                                <div class="an:mt-2 an:space-y-1 an:border-l an:border-default an:pl-3">
                                    @foreach ($step->branches as $branchLabel => $branchVisitors)
                                        @php
                                            $branchPct = $step->visitors > 0 ? (int) round($branchVisitors / $step->visitors * 100) : 0;
                                        @endphp
                                        <div class="an:flex an:items-baseline an:justify-between an:gap-3 an:text-[11px]">
                                            <span class="an:truncate an:text-muted">{{ $branchLabel }}</span>
                                            <span class="an:shrink-0 an:tabular-nums an:text-secondary">
                                                {{ number_format($branchVisitors, 0, ',', ' ') }}
                                                <span class="an:text-muted">{{ '('.$branchPct."\u{00A0}%)" }}</span>
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <p class="an:mt-1 an:text-[11px] an:text-muted">{{ __(':v par visiteur · score :s', [
                                'v' => number_format($step->value, 0, ',', ' ')."\u{00A0}pts",
                                's' => number_format($step->score, 0, ',', ' ')."\u{00A0}pts",
                            ]) }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui::card>
    @empty
        <div class="an:lg:col-span-2">
            <x-ui::empty-state
                icon="funnel"
                :title="__('Aucun tunnel')"
                :description="__('Aucun tunnel n\'est déclaré dans app/Analytics/funnels.php.')" />
        </div>
    @endforelse
</x-analytics::root>
