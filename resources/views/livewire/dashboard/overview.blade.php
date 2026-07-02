@php
    use Illuminate\Support\Str;

    $formatSeconds = function (float $seconds): string {
        $total = (int) round($seconds);
        $minutes = intdiv($total, 60);

        return $minutes > 0
            ? trim($minutes.' min '.($total % 60 > 0 ? ($total % 60).' s' : ''))
            : $total.' s';
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
    $percent = fn ($value): string => number_format((float) $value, 1, ',', ' ').' %';

    $spotlightLine = fn (string $key, callable $format): string => __(':today aujourd\'hui · :yesterday hier', [
        'today' => $format($spotlight[$key]['today']),
        'yesterday' => $format($spotlight[$key]['yesterday']),
    ]);

    $previous = $range->previous();

    $maxSources = max(array_column($topSources, 'total') ?: [0]);
    $maxCountries = max(array_column($topCountries, 'total') ?: [0]);
    $maxPages = max(array_column($topPages, 'total') ?: [0]);
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
            :description="$spotlightLine('visitors', $count)" />
        <x-ui.stat-card :label="__('Sessions')" :value="$count($headline['sessions']->current)" icon="cursor-arrow-rays"
            :trend="$deltaLabel($headline['sessions'])" :trendUp="$headline['sessions']->increased()"
            :description="$spotlightLine('sessions', $count)" />
        <x-ui.stat-card :label="__('Durée moy. session')" :value="$formatSeconds($headline['avgSeconds']->current)" icon="clock"
            :trend="$deltaLabel($headline['avgSeconds'])" :trendUp="$headline['avgSeconds']->increased()"
            :description="$spotlightLine('avgSeconds', $formatSeconds)" />
        <x-ui.stat-card :label="__('Taux de rebond')" :value="$percent($headline['bounceRate']->current)" icon="arrow-uturn-left"
            :trend="$deltaLabel($headline['bounceRate'])" :trendUp="! $headline['bounceRate']->increased()"
            :description="$spotlightLine('bounceRate', $percent)" />
    </div>

    {{-- Traffic trend (deferred, with skeleton) --}}
    <livewire:analytics-trend-chart :period="$period" :subject="$subject" />

    {{-- Section: visitors --}}
    <div>
        <x-ui.section-header :title="__('Vos visiteurs')" :description="__('D\'où viennent les sessions')" class="mb-4" />
        <div class="grid gap-6 lg:grid-cols-2">

            <x-ui.card>
                <x-ui.section-header :title="__('Sources')" class="mb-4" />
                @forelse ($topSources as $item)
                    @php $pct = $maxSources > 0 ? round($item['total'] / $maxSources * 100) : 0; @endphp
                    <div class="flex items-center gap-3 py-1.5">
                        <span class="w-36 shrink-0 truncate text-[13px] text-primary">{{ Str::headline($item['label']) }}</span>
                        <div class="relative h-1.5 flex-1 overflow-hidden rounded-full bg-elevated">
                            <div class="absolute inset-y-0 left-0 rounded-full bg-emerald-500/70" style="width: {{ $pct }}%"></div>
                        </div>
                        @include('analytics::livewire.dashboard.partials.delta', ['current' => $item['total'], 'previous' => $item['previous']])
                        <span class="w-10 shrink-0 text-right text-[12px] font-medium text-secondary">{{ number_format($item['total'], 0, ',', ' ') }}</span>
                    </div>
                @empty
                    <x-ui.empty-state icon="signal" :title="__('Aucune source')" :description="__('Aucune session sur la période.')" />
                @endforelse
            </x-ui.card>

            <x-ui.card>
                <x-ui.section-header :title="__('Pays')" class="mb-4" />
                @forelse ($topCountries as $item)
                    @php $pct = $maxCountries > 0 ? round($item['total'] / $maxCountries * 100) : 0; @endphp
                    <div class="flex items-center gap-3 py-1.5">
                        <span class="w-36 shrink-0 truncate text-[13px] font-medium uppercase text-primary">{{ $item['label'] }}</span>
                        <div class="relative h-1.5 flex-1 overflow-hidden rounded-full bg-elevated">
                            <div class="absolute inset-y-0 left-0 rounded-full bg-indigo-500/70" style="width: {{ $pct }}%"></div>
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
        <div class="grid gap-6 lg:grid-cols-3">

            <x-ui.card>
                <x-ui.section-header :title="__('Statistiques')" class="mb-4" />
                <div class="space-y-3.5">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[13px] text-secondary">{{ __('Durée moy. session') }}</span>
                        <span class="flex items-center gap-2">
                            <span class="text-[13px] font-semibold text-primary">{{ $formatSeconds($headline['avgSeconds']->current) }}</span>
                            @include('analytics::livewire.dashboard.partials.delta', ['current' => $headline['avgSeconds']->current, 'previous' => $headline['avgSeconds']->previous])
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[13px] text-secondary">{{ __('Pages par session') }}</span>
                        <span class="flex items-center gap-2">
                            <span class="text-[13px] font-semibold text-primary">{{ number_format($headline['pagesPerSession']->current, 1, ',', ' ') }}</span>
                            @include('analytics::livewire.dashboard.partials.delta', ['current' => $headline['pagesPerSession']->current, 'previous' => $headline['pagesPerSession']->previous])
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[13px] text-secondary">{{ __('Taux de rebond') }}</span>
                        <span class="flex items-center gap-2">
                            <span class="text-[13px] font-semibold text-primary">{{ number_format($headline['bounceRate']->current, 1, ',', ' ') }} %</span>
                            @include('analytics::livewire.dashboard.partials.delta', ['current' => $headline['bounceRate']->current, 'previous' => $headline['bounceRate']->previous, 'inverse' => true])
                        </span>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card>
                <x-ui.section-header :title="__('Pages les plus vues')" class="mb-4" />
                @forelse ($topPages as $item)
                    @php $pct = $maxPages > 0 ? round($item['total'] / $maxPages * 100) : 0; @endphp
                    <div class="flex items-center gap-3 py-1.5">
                        <span class="w-32 shrink-0 truncate text-[13px] text-primary" title="{{ $item['label'] }}">{{ $item['label'] }}</span>
                        <div class="relative h-1.5 flex-1 overflow-hidden rounded-full bg-elevated">
                            <div class="absolute inset-y-0 left-0 rounded-full bg-indigo-500/70" style="width: {{ $pct }}%"></div>
                        </div>
                        @include('analytics::livewire.dashboard.partials.delta', ['current' => $item['total'], 'previous' => $item['previous']])
                        <span class="w-10 shrink-0 text-right text-[12px] font-medium text-secondary">{{ number_format($item['total'], 0, ',', ' ') }}</span>
                    </div>
                @empty
                    <x-ui.empty-state icon="document" :title="__('Aucune page')" :description="__('Aucune vue sur la période.')" />
                @endforelse
            </x-ui.card>

            <x-ui.card>
                <x-ui.section-header :title="__('Clics principaux')" class="mb-4" />
                @forelse ($topClicks as $click)
                    <div class="flex items-center justify-between gap-3 py-1.5">
                        <div class="min-w-0">
                            <p class="truncate text-[13px] text-primary">{{ $click['label'] }}</p>
                            @if ($click['route'])
                                <p class="truncate text-[11px] text-muted">{{ $click['route'] }}</p>
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
