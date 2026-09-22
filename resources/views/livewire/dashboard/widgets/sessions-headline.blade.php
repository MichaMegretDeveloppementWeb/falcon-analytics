@php
    use Falcon\Analytics\Support\DurationLabel;
    use Falcon\Analytics\Support\NumberLabel;
@endphp

<x-analytics::root area="admin" class="an:grid an:grid-cols-2 an:gap-4 an:lg:grid-cols-4">
    <x-analytics::kpi-card :label="__('Sessions')" :value="NumberLabel::for($headline['sessions']->current)" icon="cursor-arrow-rays"
        :metric="$headline['sessions']">
        <div wire:key="spark-s-sessions-{{ $period }}-{{ $subject }}" class="an:mt-3"><x-analytics::sparkline :values="$sparklines['sessions']" /></div>
    </x-analytics::kpi-card>
    <x-analytics::kpi-card :label="__('Durée moy. session')" :value="DurationLabel::for($headline['avgSeconds']->current)" icon="clock"
        :metric="$headline['avgSeconds']">
        <div wire:key="spark-s-duration-{{ $period }}-{{ $subject }}" class="an:mt-3"><x-analytics::sparkline :values="$sparklines['avgSeconds']" /></div>
    </x-analytics::kpi-card>
    <x-analytics::kpi-card :label="__('Pages par session')" :value="NumberLabel::for($headline['pagesPerSession']->current, 1)" icon="rectangle-stack"
        :metric="$headline['pagesPerSession']">
        <div wire:key="spark-s-pps-{{ $period }}-{{ $subject }}" class="an:mt-3"><x-analytics::sparkline :values="$sparklines['pagesPerSession']" /></div>
    </x-analytics::kpi-card>
    <x-analytics::kpi-card :label="__('Taux de rebond')" :value="NumberLabel::percent($headline['bounceRate']->current, 1)" icon="arrow-uturn-left"
        :metric="$headline['bounceRate']" :inverse="true">
        <div wire:key="spark-s-bounce-{{ $period }}-{{ $subject }}" class="an:mt-3"><x-analytics::sparkline :values="$sparklines['bounceRate']" /></div>
    </x-analytics::kpi-card>
</x-analytics::root>
