@php
    use Illuminate\Support\Str;

    $formatSeconds = function (float $seconds): string {
        $total = (int) round($seconds);
        $minutes = intdiv($total, 60);
        $rest = $total % 60;

        if ($minutes > 0) {
            return $rest > 0 ? "{$minutes}\u{00A0}min\u{00A0}{$rest}\u{00A0}s" : "{$minutes}\u{00A0}min";
        }

        return "{$total}\u{00A0}s";
    };

    $deltaLabel = function ($metric): ?string {
        if (! $metric->hasBaseline()) {
            return $metric->current > 0 ? '+∞ %' : null;
        }

        if ($metric->changePercent() == 0.0) {
            return null;
        }

        return ($metric->changePercent() > 0 ? '+' : '').number_format($metric->changePercent(), 0, ',', ' ').' %';
    };

    $count = fn ($value): string => number_format((float) $value, 0, ',', ' ');
    $percent = fn ($value): string => number_format((float) $value, 1, ',', ' ')."\u{00A0}%";

    $spotlightLine = fn (string $key, callable $format): string => __(':today aujourd\'hui · :yesterday hier', [
        'today' => $format($spotlight[$key]['today']),
        'yesterday' => $format($spotlight[$key]['yesterday']),
    ]);

    $previous = $range->previous();

    $maxSources = max(array_column($topSources, 'total') ?: [0]);
    $maxLocalities = max(array_column($topLocalities, 'total') ?: [0]);
    $maxPages = max(array_column($topPages, 'total') ?: [0]);

    $newTotal = $newVsReturning['new'] + $newVsReturning['returning'];
    $newPct = $newTotal > 0 ? (int) round($newVsReturning['new'] / $newTotal * 100) : 0;

    $deviceTotal = array_sum($devices);
    $deviceLabels = ['desktop' => __('Ordinateur'), 'mobile' => __('Mobile'), 'tablet' => __('Tablette')];
    $devicePalette = ['#1684ea', '#7cb8f2', '#bcdcfa', '#d1d5db'];

    $sourceIcon = fn (string $category): string => [
        'direct' => 'cursor-arrow-rays',
        'organic' => 'magnifying-glass',
        'social' => 'user-group',
        'paid' => 'megaphone',
        'referral' => 'arrow-top-right-on-square',
        'email' => 'envelope',
        'campaign' => 'flag',
    ][strtolower($category)] ?? 'globe-alt';
@endphp

<div class="space-y-8">

    <x-ui.page-header
        :title="__('Vue d\'ensemble')"
        :description="__('du :from au :to', ['from' => $range->from->isoFormat('D MMM YYYY'), 'to' => $range->to->isoFormat('D MMM YYYY')])
            .' · '.__('comparé à :from - :to', ['from' => $previous->from->isoFormat('D MMM'), 'to' => $previous->to->isoFormat('D MMM')])">
        @include('analytics::livewire.dashboard.partials.filters')
    </x-ui.page-header>

    {{-- Headline KPIs: reach, volume and two engagement-quality signals --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-ui.stat-card :label="__('Visiteurs')" :value="$count($headline['visitors']->current)" icon="users"
            :trend="$deltaLabel($headline['visitors'])" :trendUp="$headline['visitors']->increased()"
            :description="$spotlightLine('visitors', $count)">
            <div wire:key="spark-visitors-{{ $period }}-{{ $subject }}" class="mt-3">
                <x-analytics::sparkline :values="$sparklines['visitors']" />
            </div>
        </x-ui.stat-card>
        <x-ui.stat-card :label="__('Sessions')" :value="$count($headline['sessions']->current)" icon="cursor-arrow-rays"
            :trend="$deltaLabel($headline['sessions'])" :trendUp="$headline['sessions']->increased()"
            :description="$spotlightLine('sessions', $count)">
            <div wire:key="spark-sessions-{{ $period }}-{{ $subject }}" class="mt-3">
                <x-analytics::sparkline :values="$sparklines['sessions']" />
            </div>
        </x-ui.stat-card>
        <x-ui.stat-card :label="__('Durée moy. session')" :value="$formatSeconds($headline['avgSeconds']->current)" icon="clock"
            :trend="$deltaLabel($headline['avgSeconds'])" :trendUp="$headline['avgSeconds']->increased()"
            :description="$spotlightLine('avgSeconds', $formatSeconds)">
            <div wire:key="spark-duration-{{ $period }}-{{ $subject }}" class="mt-3">
                <x-analytics::sparkline :values="$sparklines['avgSeconds']" />
            </div>
        </x-ui.stat-card>
        <x-ui.stat-card :label="__('Taux de rebond')" :value="$percent($headline['bounceRate']->current)" icon="arrow-uturn-left"
            :trend="$deltaLabel($headline['bounceRate'])" :trendUp="! $headline['bounceRate']->increased()"
            :description="$spotlightLine('bounceRate', $percent)">
            <div wire:key="spark-bounce-{{ $period }}-{{ $subject }}" class="mt-3">
                <x-analytics::sparkline :values="$sparklines['bounceRate']" />
            </div>
        </x-ui.stat-card>
    </div>

    {{-- Traffic trend (deferred, with skeleton) --}}
    <livewire:analytics-trend-chart :period="$period" :subject="$subject" />

    {{-- Section: visitors --}}
    <div>
        <x-ui.section-header :title="__('Vos visiteurs')" :description="__('D\'où viennent les sessions')" class="mb-4" />

        {{-- Audience composition donuts --}}
        <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">

            <x-ui.card>
                <x-ui.section-header :title="__('Nouveaux vs récurrents')" class="mb-4" />
                @if ($newTotal > 0)
                    <div class="flex items-center gap-5">
                        <div wire:key="donut-audience-{{ $period }}-{{ $subject }}">
                            <x-analytics::donut
                                :labels="[__('Nouveaux'), __('Récurrents')]"
                                :values="[$newVsReturning['new'], $newVsReturning['returning']]"
                                :colors="['#1684ea', '#bcdcfa']"
                                :total="number_format($newTotal, 0, ',', ' ')"
                                :caption="__('visiteurs')" />
                        </div>
                        <div class="flex-1 space-y-2.5">
                            <div class="flex items-center justify-between gap-2">
                                <span class="flex items-center gap-2 text-[13px] text-secondary"><span class="h-2 w-2 rounded-full" style="background:#1684ea"></span>{{ __('Nouveaux') }}</span>
                                <span class="text-[13px]"><span class="font-semibold text-primary">{{ $newPct }} %</span> <span class="text-muted">{{ number_format($newVsReturning['new'], 0, ',', ' ') }}</span></span>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="flex items-center gap-2 text-[13px] text-secondary"><span class="h-2 w-2 rounded-full" style="background:#bcdcfa"></span>{{ __('Récurrents') }}</span>
                                <span class="text-[13px]"><span class="font-semibold text-primary">{{ 100 - $newPct }} %</span> <span class="text-muted">{{ number_format($newVsReturning['returning'], 0, ',', ' ') }}</span></span>
                            </div>
                        </div>
                    </div>
                @else
                    <x-ui.empty-state icon="users" :title="__('Aucun visiteur')" :description="__('Aucune session sur la période.')" />
                @endif
            </x-ui.card>

            <x-ui.card>
                <x-ui.section-header :title="__('Appareils')" class="mb-4" />
                @if ($deviceTotal > 0)
                    <div class="flex items-center gap-5">
                        <div wire:key="donut-devices-{{ $period }}-{{ $subject }}">
                            <x-analytics::donut
                                :labels="collect($devices)->keys()->map(fn ($d) => $deviceLabels[$d] ?? Str::title($d))->all()"
                                :values="array_values($devices)"
                                :colors="array_slice($devicePalette, 0, count($devices))"
                                :total="number_format($deviceTotal, 0, ',', ' ')"
                                :caption="__('sessions')" />
                        </div>
                        <div class="flex-1 space-y-2.5">
                            @foreach ($devices as $device => $count)
                                <div class="flex items-center justify-between gap-2">
                                    <span class="flex items-center gap-2 text-[13px] text-secondary"><span class="h-2 w-2 rounded-full" style="background:{{ $devicePalette[$loop->index] ?? '#d1d5db' }}"></span>{{ $deviceLabels[$device] ?? Str::title($device) }}</span>
                                    <span class="text-[13px]"><span class="font-semibold text-primary">{{ (int) round($count / $deviceTotal * 100) }} %</span> <span class="text-muted">{{ number_format($count, 0, ',', ' ') }}</span></span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <x-ui.empty-state icon="device-phone-mobile" :title="__('Aucun appareil')" :description="__('Aucune session sur la période.')" />
                @endif
            </x-ui.card>

        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

            <x-ui.card>
                <x-ui.section-header :title="__('Sources')" :description="__('Par canal d\'acquisition')" class="mb-4" />
                @forelse ($topSources as $item)
                    @php $pct = $maxSources > 0 ? round($item['total'] / $maxSources * 100) : 0; @endphp
                    <div class="flex items-center gap-3 py-1.5">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-elevated">
                            <x-ui.icon :name="$sourceIcon($item['label'])" class="h-3.5 w-3.5 text-secondary" />
                        </span>
                        <span class="w-28 shrink-0 truncate text-[13px] text-primary"><x-analytics::source :value="$item['label']" /></span>
                        <div class="relative h-1.5 flex-1 overflow-hidden rounded-full bg-elevated">
                            <div class="absolute inset-y-0 left-0 rounded-full bg-[#1684ea]/70" style="width: {{ $pct }}%"></div>
                        </div>
                        @include('analytics::livewire.dashboard.partials.delta', ['current' => $item['total'], 'previous' => $item['previous']])
                        <span class="w-10 shrink-0 text-right text-[12px] font-medium text-secondary">{{ number_format($item['total'], 0, ',', ' ') }}</span>
                    </div>
                @empty
                    <x-ui.empty-state icon="signal" :title="__('Aucune source')" :description="__('Aucune session sur la période.')" />
                @endforelse
            </x-ui.card>

            <x-ui.card>
                <x-ui.section-header :title="__('Localités')" :description="__('Pays et ville')" class="mb-4" />
                @forelse ($topLocalities as $item)
                    @php $pct = $maxLocalities > 0 ? round($item['total'] / $maxLocalities * 100) : 0; @endphp
                    <div class="flex items-center gap-3 py-1.5">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-elevated">
                            <x-ui.icon name="map-pin" class="h-3.5 w-3.5 text-secondary" />
                        </span>
                        <span class="w-40 shrink-0 truncate text-[13px] text-primary">
                            <x-analytics::country :code="$item['country']" :city="$item['city']" />
                        </span>
                        <div class="relative h-1.5 flex-1 overflow-hidden rounded-full bg-elevated">
                            <div class="absolute inset-y-0 left-0 rounded-full bg-[#1684ea]/70" style="width: {{ $pct }}%"></div>
                        </div>
                        <span class="w-10 shrink-0 text-right text-[12px] font-medium text-secondary">{{ number_format($item['total'], 0, ',', ' ') }}</span>
                    </div>
                @empty
                    <x-ui.empty-state icon="globe-alt" :title="__('Aucune localité')" :description="__('Géolocalisation indisponible.')" />
                @endforelse
            </x-ui.card>

        </div>
    </div>

    {{-- Section: engagement --}}
    <div>
        <x-ui.section-header :title="__('Engagement')" :description="__('Comment les visiteurs interagissent')" class="mb-4" />
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

            <x-ui.card>
                <x-ui.section-header :title="__('Statistiques')" :description="__('Sur la période')" class="mb-4" />
                <div class="space-y-2">
                    <div class="flex items-center gap-3 py-1">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-elevated">
                            <x-ui.icon name="document-text" class="h-3.5 w-3.5 text-secondary" />
                        </span>
                        <span class="flex-1 text-[13px] text-secondary">{{ __('Pages vues') }}</span>
                        <span class="flex items-center gap-2">
                            <span class="text-[13px] font-semibold text-primary">{{ number_format($headline['pageviews']->current, 0, ',', ' ') }}</span>
                            @include('analytics::livewire.dashboard.partials.delta', ['current' => $headline['pageviews']->current, 'previous' => $headline['pageviews']->previous])
                        </span>
                    </div>
                    <div class="flex items-center gap-3 py-1">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-elevated">
                            <x-ui.icon name="document-duplicate" class="h-3.5 w-3.5 text-secondary" />
                        </span>
                        <span class="flex-1 text-[13px] text-secondary">{{ __('Pages par session') }}</span>
                        <span class="flex items-center gap-2">
                            <span class="text-[13px] font-semibold text-primary">{{ number_format($headline['pagesPerSession']->current, 1, ',', ' ') }}</span>
                            @include('analytics::livewire.dashboard.partials.delta', ['current' => $headline['pagesPerSession']->current, 'previous' => $headline['pagesPerSession']->previous])
                        </span>
                    </div>
                    <div class="flex items-center gap-3 py-1">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-elevated">
                            <x-ui.icon name="user-plus" class="h-3.5 w-3.5 text-secondary" />
                        </span>
                        <span class="flex-1 text-[13px] text-secondary">{{ __('Nouveaux visiteurs') }}</span>
                        <span class="flex items-center gap-2">
                            <span class="text-[13px] font-semibold text-primary">{{ number_format($newVisitorRate->current, 1, ',', ' ') }} %</span>
                            @include('analytics::livewire.dashboard.partials.delta', ['current' => $newVisitorRate->current, 'previous' => $newVisitorRate->previous])
                        </span>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card>
                <x-ui.section-header :title="__('Pages les plus vues')" :description="__('Les plus consultées')" class="mb-4" />
                @forelse ($topPages as $item)
                    @php $pct = $maxPages > 0 ? round($item['total'] / $maxPages * 100) : 0; @endphp
                    <div class="flex items-center gap-3 py-1.5">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-elevated">
                            <x-ui.icon name="document-text" class="h-3.5 w-3.5 text-secondary" />
                        </span>
                        <span class="w-24 shrink-0 truncate text-[13px] text-primary"><x-analytics::page-url :url="$item['label']" /></span>
                        <div class="relative h-1.5 flex-1 overflow-hidden rounded-full bg-elevated">
                            <div class="absolute inset-y-0 left-0 rounded-full bg-[#1684ea]/70" style="width: {{ $pct }}%"></div>
                        </div>
                        @include('analytics::livewire.dashboard.partials.delta', ['current' => $item['total'], 'previous' => $item['previous']])
                        <span class="w-10 shrink-0 text-right text-[12px] font-medium text-secondary">{{ number_format($item['total'], 0, ',', ' ') }}</span>
                    </div>
                @empty
                    <x-ui.empty-state icon="document" :title="__('Aucune page')" :description="__('Aucune vue sur la période.')" />
                @endforelse
            </x-ui.card>

            <x-ui.card>
                <x-ui.section-header :title="__('Clics principaux')" :description="__('Boutons et liens cliqués')" class="mb-4" />
                @forelse ($topClicks as $click)
                    <div class="flex items-center gap-3 py-1.5">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-elevated">
                            <x-ui.icon name="cursor-arrow-rays" class="h-3.5 w-3.5 text-secondary" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13px] text-primary">{{ $click['label'] }}</p>
                            @if ($click['route'])
                                <p class="truncate text-[11px] text-muted"><x-analytics::page-url :route="$click['route']" /></p>
                            @endif
                        </div>
                        <span class="shrink-0 text-[12px] font-medium text-secondary">{{ number_format($click['total'], 0, ',', ' ') }}</span>
                    </div>
                @empty
                    <x-ui.empty-state icon="cursor-arrow-rays" :title="__('Aucun clic')" :description="__('Aucun clic capté sur la période.')" />
                @endforelse
            </x-ui.card>

        </div>
    </div>

</div>
