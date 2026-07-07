@php
    $formatSeconds = function (float $seconds): string {
        $total = (int) round($seconds);
        $minutes = intdiv($total, 60);
        $rest = $total % 60;

        if ($minutes > 0) {
            return $rest > 0 ? "{$minutes}\u{00A0}min\u{00A0}{$rest}\u{00A0}s" : "{$minutes}\u{00A0}min";
        }

        return "{$total}\u{00A0}s";
    };

    $count = fn ($value): string => number_format((float) $value, 0, ',', ' ');
    $percent = fn ($value): string => number_format((float) $value, 1, ',', ' ')."\u{00A0}%";

    $spotlightLine = fn (string $key, callable $format): string => __(':today aujourd\'hui · :yesterday hier', [
        'today' => $format($spotlight[$key]['today']),
        'yesterday' => $format($spotlight[$key]['yesterday']),
    ]);
@endphp

<div class="space-y-8">

    {{-- Headline KPIs : reach, volume and two engagement-quality signals --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-analytics::kpi-card :label="__('Visiteurs')" :value="$count($headline['visitors']->current)" icon="users"
            :metric="$headline['visitors']" :description="$spotlightLine('visitors', $count)">
            <div wire:key="spark-visitors-{{ $period }}-{{ $subject }}" class="mt-3">
                <x-analytics::sparkline :values="$sparklines['visitors']" />
            </div>
        </x-analytics::kpi-card>
        <x-analytics::kpi-card :label="__('Sessions')" :value="$count($headline['sessions']->current)" icon="cursor-arrow-rays"
            :metric="$headline['sessions']" :description="$spotlightLine('sessions', $count)">
            <div wire:key="spark-sessions-{{ $period }}-{{ $subject }}" class="mt-3">
                <x-analytics::sparkline :values="$sparklines['sessions']" />
            </div>
        </x-analytics::kpi-card>
        <x-analytics::kpi-card :label="__('Durée moy. session')" :value="$formatSeconds($headline['avgSeconds']->current)" icon="clock"
            :metric="$headline['avgSeconds']" :description="$spotlightLine('avgSeconds', $formatSeconds)">
            <div wire:key="spark-duration-{{ $period }}-{{ $subject }}" class="mt-3">
                <x-analytics::sparkline :values="$sparklines['avgSeconds']" />
            </div>
        </x-analytics::kpi-card>
        <x-analytics::kpi-card :label="__('Taux de rebond')" :value="$percent($headline['bounceRate']->current)" icon="arrow-uturn-left"
            :metric="$headline['bounceRate']" :inverse="true" :description="$spotlightLine('bounceRate', $percent)">
            <div wire:key="spark-bounce-{{ $period }}-{{ $subject }}" class="mt-3">
                <x-analytics::sparkline :values="$sparklines['bounceRate']" />
            </div>
        </x-analytics::kpi-card>
    </div>

    {{-- Engagement stats (same headline read as the KPIs) --}}
    <x-ui.card>
        <x-ui.section-header :title="__('Statistiques')" :description="__('Sur la période')" class="mb-4" />
        <div class="grid grid-cols-1 gap-x-8 gap-y-2 sm:grid-cols-2">
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
        </div>
    </x-ui.card>

</div>
