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

    $percent = fn ($v): string => number_format((float) $v, 1, ',', ' ')."\u{00A0}%";
@endphp

<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <x-analytics::kpi-card :label="__('Sessions')" :value="number_format($headline['sessions']->current, 0, ',', ' ')" icon="cursor-arrow-rays"
        :metric="$headline['sessions']">
        <div wire:key="spark-s-sessions-{{ $period }}-{{ $subject }}" class="mt-3"><x-analytics::sparkline :values="$sparklines['sessions']" /></div>
    </x-analytics::kpi-card>
    <x-analytics::kpi-card :label="__('Durée moy. session')" :value="$formatSeconds($headline['avgSeconds']->current)" icon="clock"
        :metric="$headline['avgSeconds']">
        <div wire:key="spark-s-duration-{{ $period }}-{{ $subject }}" class="mt-3"><x-analytics::sparkline :values="$sparklines['avgSeconds']" /></div>
    </x-analytics::kpi-card>
    <x-analytics::kpi-card :label="__('Pages par session')" :value="number_format($headline['pagesPerSession']->current, 1, ',', ' ')" icon="rectangle-stack"
        :metric="$headline['pagesPerSession']">
        <div wire:key="spark-s-pps-{{ $period }}-{{ $subject }}" class="mt-3"><x-analytics::sparkline :values="$sparklines['pagesPerSession']" /></div>
    </x-analytics::kpi-card>
    <x-analytics::kpi-card :label="__('Taux de rebond')" :value="$percent($headline['bounceRate']->current)" icon="arrow-uturn-left"
        :metric="$headline['bounceRate']" :inverse="true">
        <div wire:key="spark-s-bounce-{{ $period }}-{{ $subject }}" class="mt-3"><x-analytics::sparkline :values="$sparklines['bounceRate']" /></div>
    </x-analytics::kpi-card>
</div>
