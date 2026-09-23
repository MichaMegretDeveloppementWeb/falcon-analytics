@php
    use Falcon\Analytics\Support\ChartPalette;
    use Falcon\Analytics\Support\DeviceLabel;
    use Falcon\Analytics\Support\NumberLabel;

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
                            :total="NumberLabel::for($newTotal)"
                            :caption="$newTotal > 1 ? __('visiteurs') : __('visiteur')" />
                    </div>
                    {{--
                        A grid, not a stretch: the column of numbers starts after the longest
                        label, close enough to read each pair at a glance, and aligned from one
                        row to the next so they can still be compared.

                        One number per row: the share is read off the doughnut, which is there
                        for that, and two numbers of similar size side by side leave the reader
                        unsure which one to read.
                    --}}
                    <dl class="an:grid an:min-w-0 an:max-w-[15rem] an:flex-1 an:grid-cols-[minmax(0,1fr)_auto] an:items-center an:gap-x-6 an:gap-y-2.5">
                        <dt class="an:flex an:items-center an:gap-2 an:text-[13px] an:text-secondary"><span class="an:h-2 an:w-2 an:shrink-0 an:rounded-full" style="background:var(--an-series-1)"></span>{{ __('Nouveaux') }}</dt>
                        <dd class="an:text-right an:text-[13px] an:font-semibold an:tabular-nums an:text-primary" title="{{ NumberLabel::percent($newPct) }}">{{ NumberLabel::for($newVsReturning['new']) }}</dd>

                        <dt class="an:flex an:items-center an:gap-2 an:text-[13px] an:text-secondary"><span class="an:h-2 an:w-2 an:shrink-0 an:rounded-full" style="background:var(--an-series-5)"></span>{{ __('Récurrents') }}</dt>
                        <dd class="an:text-right an:text-[13px] an:font-semibold an:tabular-nums an:text-primary" title="{{ NumberLabel::percent(100 - $newPct) }}">{{ NumberLabel::for($newVsReturning['returning']) }}</dd>
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
                            :total="NumberLabel::for($deviceTotal)"
                            :caption="$deviceTotal > 1 ? __('sessions') : __('session')" />
                    </div>
                    <dl class="an:grid an:min-w-0 an:max-w-[15rem] an:flex-1 an:grid-cols-[minmax(0,1fr)_auto] an:items-center an:gap-x-6 an:gap-y-2.5">
                        @foreach ($devices as $device => $count)
                            <dt class="an:flex an:min-w-0 an:items-center an:gap-2 an:text-[13px] an:text-secondary">
                                <span class="an:h-2 an:w-2 an:shrink-0 an:rounded-full" style="background:var({{ $devicePalette[$loop->index] ?? '--an-series-6' }})"></span>
                                <span class="an:truncate">{{ DeviceLabel::for($device) }}</span>
                            </dt>
                            <dd class="an:text-right an:text-[13px] an:font-semibold an:tabular-nums an:text-primary" title="{{ NumberLabel::percent($count / $deviceTotal * 100) }}">{{ NumberLabel::for($count) }}</dd>
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
