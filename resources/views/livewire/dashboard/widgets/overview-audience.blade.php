@php
    use Falcon\Analytics\Support\ChartPalette;
    use Falcon\Analytics\Support\DeviceLabel;

    $newTotal = $newVsReturning['new'] + $newVsReturning['returning'];
    $newPct = $newTotal > 0 ? (int) round($newVsReturning['new'] / $newTotal * 100) : 0;
    $deviceTotal = array_sum($devices);
    // Every other step of the ramp: four device kinds read better with a gap
    // between their shades than with four neighbours.
    $devicePalette = [
        ChartPalette::SERIES[0],
        ChartPalette::SERIES[2],
        ChartPalette::SERIES[4],
        ChartPalette::SERIES[5],
    ];
@endphp

{{-- The root wraps the card rather than replacing it: the card is a component
     of the kit, and it is precisely the one that has to be drawn inside the
     package's context. --}}
<x-analytics::root area="admin">
<x-ui::card>
    <div class="an:grid an:grid-cols-1 an:divide-y an:divide-subtle an:lg:grid-cols-2 an:lg:divide-x an:lg:divide-y-0">

        <div class="an:pb-5 an:lg:pb-0 an:lg:pr-8">
            <div class="an:mb-4 an:flex an:items-center an:justify-between an:gap-2">
                <x-ui::section-header :title="__('Nouveaux vs récurrents')" />
                <span class="an:flex an:items-center an:gap-1.5 an:text-[11px] an:text-muted">{{ __('Nouveaux') }} @include('analytics::livewire.dashboard.partials.delta', ['current' => $newVisitorRate->current, 'previous' => $newVisitorRate->previous])</span>
            </div>
            @if ($newTotal > 0)
                <div class="an:flex an:items-center an:gap-6">
                    <div wire:key="donut-audience-{{ $period }}-{{ $subject }}">
                        <x-analytics::donut
                            :labels="[__('Nouveaux'), __('Récurrents')]"
                            :values="[$newVsReturning['new'], $newVsReturning['returning']]"
                            :colors="[ChartPalette::SERIES[0], ChartPalette::SERIES[4]]"
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
                    <dl class="an:grid an:min-w-0 an:max-w-[15rem] an:flex-1 an:grid-cols-[minmax(0,1fr)_auto] an:items-center an:gap-x-6 an:gap-y-2.5">
                        <dt class="an:flex an:items-center an:gap-2 an:text-[13px] an:text-secondary"><span class="an:h-2 an:w-2 an:shrink-0 an:rounded-full" style="background:var(--an-series-1)"></span>{{ __('Nouveaux') }}</dt>
                        <dd class="an:text-right an:text-[13px] an:font-semibold an:tabular-nums an:text-primary" title="{{ $newPct."\u{00A0}%" }}">{{ number_format($newVsReturning['new'], 0, ',', ' ') }}</dd>

                        <dt class="an:flex an:items-center an:gap-2 an:text-[13px] an:text-secondary"><span class="an:h-2 an:w-2 an:shrink-0 an:rounded-full" style="background:var(--an-series-5)"></span>{{ __('Récurrents') }}</dt>
                        <dd class="an:text-right an:text-[13px] an:font-semibold an:tabular-nums an:text-primary" title="{{ (100 - $newPct)."\u{00A0}%" }}">{{ number_format($newVsReturning['returning'], 0, ',', ' ') }}</dd>
                    </dl>
                </div>
            @else
                <x-ui::empty-state icon="users" :title="__('Aucun visiteur')" :description="__('Aucune session sur la période.')" />
            @endif
        </div>

        <div class="an:pt-5 an:lg:pl-8 an:lg:pt-0">
            <x-ui::section-header :title="__('Appareils')" class="an:mb-4" />
            @if ($deviceTotal > 0)
                <div class="an:flex an:items-center an:gap-6">
                    <div wire:key="donut-devices-{{ $period }}-{{ $subject }}">
                        <x-analytics::donut
                            :labels="collect($devices)->keys()->map(fn ($d) => DeviceLabel::for($d))->all()"
                            :values="array_values($devices)"
                            :colors="array_slice($devicePalette, 0, count($devices))"
                            :total="number_format($deviceTotal, 0, ',', ' ')"
                            :caption="__('sessions')" />
                    </div>
                    <dl class="an:grid an:min-w-0 an:max-w-[15rem] an:flex-1 an:grid-cols-[minmax(0,1fr)_auto] an:items-center an:gap-x-6 an:gap-y-2.5">
                        @foreach ($devices as $device => $count)
                            <dt class="an:flex an:min-w-0 an:items-center an:gap-2 an:text-[13px] an:text-secondary">
                                <span class="an:h-2 an:w-2 an:shrink-0 an:rounded-full" style="background:var({{ $devicePalette[$loop->index] ?? '--an-series-6' }})"></span>
                                <span class="an:truncate">{{ DeviceLabel::for($device) }}</span>
                            </dt>
                            <dd class="an:text-right an:text-[13px] an:font-semibold an:tabular-nums an:text-primary" title="{{ ((int) round($count / $deviceTotal * 100))."\u{00A0}%" }}">{{ number_format($count, 0, ',', ' ') }}</dd>
                        @endforeach
                    </dl>
                </div>
            @else
                <x-ui::empty-state icon="device-phone-mobile" :title="__('Aucun appareil')" :description="__('Aucune session sur la période.')" />
            @endif
        </div>

    </div>
</x-ui::card>
</x-analytics::root>
