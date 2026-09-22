@php
    use Falcon\Analytics\Support\DurationLabel;

    $count = fn ($value): string => number_format((float) $value, 0, ',', ' ');
    $percent = fn ($value): string => number_format((float) $value, 1, ',', ' ')."\u{00A0}%";

    $spotlightLine = fn (string $key, callable $format): string => __(':today aujourd\'hui · :yesterday hier', [
        'today' => $format($spotlight[$key]['today']),
        'yesterday' => $format($spotlight[$key]['yesterday']),
    ]);
@endphp

<x-analytics::root area="admin" class="an:space-y-8">

    {{-- Headline KPIs : reach, volume and two engagement-quality signals --}}
    <div class="an:grid an:grid-cols-2 an:gap-4 an:lg:grid-cols-4">
        <x-analytics::kpi-card :label="__('Visiteurs')" :value="$count($headline['visitors']->current)" icon="users"
            :metric="$headline['visitors']" :description="$spotlightLine('visitors', $count)">
            <div wire:key="spark-visitors-{{ $period }}-{{ $subject }}" class="an:mt-3">
                <x-analytics::sparkline :values="$sparklines['visitors']" />
            </div>
        </x-analytics::kpi-card>
        <x-analytics::kpi-card :label="__('Sessions')" :value="$count($headline['sessions']->current)" icon="cursor-arrow-rays"
            :metric="$headline['sessions']" :description="$spotlightLine('sessions', $count)">
            <div wire:key="spark-sessions-{{ $period }}-{{ $subject }}" class="an:mt-3">
                <x-analytics::sparkline :values="$sparklines['sessions']" />
            </div>
        </x-analytics::kpi-card>
        <x-analytics::kpi-card :label="__('Durée moy. session')" :value="DurationLabel::for($headline['avgSeconds']->current)" icon="clock"
            :metric="$headline['avgSeconds']" :description="$spotlightLine('avgSeconds', DurationLabel::for(...))">
            <div wire:key="spark-duration-{{ $period }}-{{ $subject }}" class="an:mt-3">
                <x-analytics::sparkline :values="$sparklines['avgSeconds']" />
            </div>
        </x-analytics::kpi-card>
        <x-analytics::kpi-card :label="__('Taux de rebond')" :value="$percent($headline['bounceRate']->current)" icon="arrow-uturn-left"
            :metric="$headline['bounceRate']" :inverse="true" :description="$spotlightLine('bounceRate', $percent)">
            <div wire:key="spark-bounce-{{ $period }}-{{ $subject }}" class="an:mt-3">
                <x-analytics::sparkline :values="$sparklines['bounceRate']" />
            </div>
        </x-analytics::kpi-card>
    </div>

    {{-- Engagement stats --}}
    <x-ui::card>
        <x-ui::section-header :title="__('Statistiques')" :description="__('Sur la période')" class="an:mb-4" />
        <div class="an:grid an:grid-cols-1 an:gap-x-8 an:gap-y-2 an:sm:grid-cols-2">
            <div class="an:flex an:items-center an:gap-3 an:py-1">
                <span class="an:flex an:h-7 an:w-7 an:shrink-0 an:items-center an:justify-center an:rounded-lg an:bg-elevated">
                    <x-ui::icon name="document-text" class="an:h-3.5 an:w-3.5 an:text-secondary" />
                </span>
                <span class="an:text-[13px] an:text-secondary">{{ __('Pages vues') }}</span>
                <span class="an:flex an:items-center an:gap-2">
                    <span class="an:text-[13px] an:font-semibold an:tabular-nums an:text-primary">{{ number_format($headline['pageviews']->current, 0, ',', ' ') }}</span>
                    @include('analytics::livewire.dashboard.partials.delta', ['current' => $headline['pageviews']->current, 'previous' => $headline['pageviews']->previous])
                </span>
            </div>
            <div class="an:flex an:items-center an:gap-3 an:py-1">
                <span class="an:flex an:h-7 an:w-7 an:shrink-0 an:items-center an:justify-center an:rounded-lg an:bg-elevated">
                    <x-ui::icon name="document-duplicate" class="an:h-3.5 an:w-3.5 an:text-secondary" />
                </span>
                <span class="an:text-[13px] an:text-secondary">{{ __('Pages par session') }}</span>
                <span class="an:flex an:items-center an:gap-2">
                    <span class="an:text-[13px] an:font-semibold an:tabular-nums an:text-primary">{{ number_format($headline['pagesPerSession']->current, 1, ',', ' ') }}</span>
                    @include('analytics::livewire.dashboard.partials.delta', ['current' => $headline['pagesPerSession']->current, 'previous' => $headline['pagesPerSession']->previous])
                </span>
            </div>
        </div>
    </x-ui::card>

</x-analytics::root>
