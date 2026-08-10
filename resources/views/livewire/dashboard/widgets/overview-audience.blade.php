@php
    use Falcon\Analytics\Support\DeviceLabel;

    $newTotal = $newVsReturning['new'] + $newVsReturning['returning'];
    $newPct = $newTotal > 0 ? (int) round($newVsReturning['new'] / $newTotal * 100) : 0;
    $deviceTotal = array_sum($devices);
    $devicePalette = ['#1684ea', '#7cb8f2', '#bcdcfa', '#d1d5db'];
@endphp

<x-ui.card>
    <div class="grid grid-cols-1 divide-y divide-subtle lg:grid-cols-2 lg:divide-x lg:divide-y-0">

        <div class="pb-5 lg:pb-0 lg:pr-8">
            <div class="mb-4 flex items-center justify-between gap-2">
                <x-ui.section-header :title="__('Nouveaux vs récurrents')" />
                <span class="flex items-center gap-1.5 text-[11px] text-muted">{{ __('Nouveaux') }} @include('analytics::livewire.dashboard.partials.delta', ['current' => $newVisitorRate->current, 'previous' => $newVisitorRate->previous])</span>
            </div>
            @if ($newTotal > 0)
                <div class="flex items-center gap-6">
                    <div wire:key="donut-audience-{{ $period }}-{{ $subject }}">
                        <x-analytics::donut
                            :labels="[__('Nouveaux'), __('Récurrents')]"
                            :values="[$newVsReturning['new'], $newVsReturning['returning']]"
                            :colors="['#1684ea', '#bcdcfa']"
                            :total="number_format($newTotal, 0, ',', ' ')"
                            :caption="__('visiteurs')" />
                    </div>
                    {{--
                        Une grille, pas un etirement. La valeur se posait au bord de la carte,
                        a l'autre bout d'un vide que rien ne traversait : le libelle et son
                        nombre etaient les deux choses les plus eloignees de la ligne. La
                        colonne des nombres commence maintenant apres le plus long libelle,
                        assez pres pour qu'on lise la paire d'un coup, et alignee d'une ligne
                        a l'autre pour qu'on puisse encore comparer.

                        Un seul nombre aussi : la part se lit sur le beignet, qui est la pour
                        cela, et deux nombres de taille voisine cote a cote obligeaient a
                        decider lequel on lit.
                    --}}
                    <dl class="grid min-w-0 max-w-[15rem] flex-1 grid-cols-[minmax(0,1fr)_auto] items-center gap-x-6 gap-y-2.5">
                        <dt class="flex items-center gap-2 text-[13px] text-secondary"><span class="h-2 w-2 shrink-0 rounded-full" style="background:#1684ea"></span>{{ __('Nouveaux') }}</dt>
                        <dd class="text-right text-[13px] font-semibold tabular-nums text-primary" title="{{ $newPct."\u{00A0}%" }}">{{ number_format($newVsReturning['new'], 0, ',', ' ') }}</dd>

                        <dt class="flex items-center gap-2 text-[13px] text-secondary"><span class="h-2 w-2 shrink-0 rounded-full" style="background:#bcdcfa"></span>{{ __('Récurrents') }}</dt>
                        <dd class="text-right text-[13px] font-semibold tabular-nums text-primary" title="{{ (100 - $newPct)."\u{00A0}%" }}">{{ number_format($newVsReturning['returning'], 0, ',', ' ') }}</dd>
                    </dl>
                </div>
            @else
                <x-ui.empty-state icon="users" :title="__('Aucun visiteur')" :description="__('Aucune session sur la période.')" />
            @endif
        </div>

        <div class="pt-5 lg:pl-8 lg:pt-0">
            <x-ui.section-header :title="__('Appareils')" class="mb-4" />
            @if ($deviceTotal > 0)
                <div class="flex items-center gap-6">
                    <div wire:key="donut-devices-{{ $period }}-{{ $subject }}">
                        <x-analytics::donut
                            :labels="collect($devices)->keys()->map(fn ($d) => DeviceLabel::for($d))->all()"
                            :values="array_values($devices)"
                            :colors="array_slice($devicePalette, 0, count($devices))"
                            :total="number_format($deviceTotal, 0, ',', ' ')"
                            :caption="__('sessions')" />
                    </div>
                    <dl class="grid min-w-0 max-w-[15rem] flex-1 grid-cols-[minmax(0,1fr)_auto] items-center gap-x-6 gap-y-2.5">
                        @foreach ($devices as $device => $count)
                            <dt class="flex min-w-0 items-center gap-2 text-[13px] text-secondary">
                                <span class="h-2 w-2 shrink-0 rounded-full" style="background:{{ $devicePalette[$loop->index] ?? '#d1d5db' }}"></span>
                                <span class="truncate">{{ DeviceLabel::for($device) }}</span>
                            </dt>
                            <dd class="text-right text-[13px] font-semibold tabular-nums text-primary" title="{{ ((int) round($count / $deviceTotal * 100))."\u{00A0}%" }}">{{ number_format($count, 0, ',', ' ') }}</dd>
                        @endforeach
                    </dl>
                </div>
            @else
                <x-ui.empty-state icon="device-phone-mobile" :title="__('Aucun appareil')" :description="__('Aucune session sur la période.')" />
            @endif
        </div>

    </div>
</x-ui.card>
