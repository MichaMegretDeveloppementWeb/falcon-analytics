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
                    <div class="min-w-0 flex-1 space-y-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2 text-[13px] text-secondary"><span class="h-2 w-2 rounded-full" style="background:#1684ea"></span>{{ __('Nouveaux') }}</span>
                            <span class="text-[13px]"><span class="font-semibold text-primary">{{ $newPct."\u{00A0}%" }}</span> <span class="text-muted">{{ number_format($newVsReturning['new'], 0, ',', ' ') }}</span></span>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2 text-[13px] text-secondary"><span class="h-2 w-2 rounded-full" style="background:#bcdcfa"></span>{{ __('Récurrents') }}</span>
                            <span class="text-[13px]"><span class="font-semibold text-primary">{{ (100 - $newPct)."\u{00A0}%" }}</span> <span class="text-muted">{{ number_format($newVsReturning['returning'], 0, ',', ' ') }}</span></span>
                        </div>
                    </div>
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
                    <div class="min-w-0 flex-1 space-y-2.5">
                        @foreach ($devices as $device => $count)
                            <div class="flex items-center justify-between gap-2">
                                <span class="flex items-center gap-2 text-[13px] text-secondary"><span class="h-2 w-2 rounded-full" style="background:{{ $devicePalette[$loop->index] ?? '#d1d5db' }}"></span>{{ DeviceLabel::for($device) }}</span>
                                <span class="text-[13px]"><span class="font-semibold text-primary">{{ ((int) round($count / $deviceTotal * 100))."\u{00A0}%" }}</span> <span class="text-muted">{{ number_format($count, 0, ',', ' ') }}</span></span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <x-ui.empty-state icon="device-phone-mobile" :title="__('Aucun appareil')" :description="__('Aucune session sur la période.')" />
            @endif
        </div>

    </div>
</x-ui.card>
