<x-analytics::root area="admin" class="an:space-y-8">

    @include('analytics::livewire.dashboard.partials.attribution-ceiling')

    {{-- KPIs with integrated sparklines --}}
    <div class="an:grid an:grid-cols-2 an:gap-4 an:lg:grid-cols-4">
        <x-analytics::kpi-card :label="__('Sessions issues de pubs')" :value="number_format($sessions, 0, ',', ' ')" icon="cursor-arrow-rays" :metric="$sessionsDelta">
            <div wire:key="spark-sessions-{{ $range->days }}-{{ $subject }}" class="an:mt-3">
                <x-analytics::sparkline :values="$trendData" />
            </div>
        </x-analytics::kpi-card>
        <x-analytics::kpi-card :label="__('Visiteurs issus de pubs')" :value="number_format($visitors, 0, ',', ' ')" icon="users" :metric="$visitorsDelta">
            <div wire:key="spark-visitors-{{ $range->days }}-{{ $subject }}" class="an:mt-3">
                <x-analytics::sparkline :values="$trendData" />
            </div>
        </x-analytics::kpi-card>
        <x-analytics::kpi-card :label="__('Conversions')" :value="number_format($conversions, 0, ',', ' ')" icon="check-circle" :metric="$conversionsDelta">
            <div wire:key="spark-conv-{{ $range->days }}-{{ $subject }}" class="an:mt-3"><x-analytics::sparkline :values="$conversionsTrend" color="--an-conversion" /></div>
        </x-analytics::kpi-card>
        <x-analytics::kpi-card :label="__('Taux de conversion')" :value="$rateLabel" icon="arrow-trending-up" :metric="$rateDelta">
            <div wire:key="spark-rate-{{ $range->days }}-{{ $subject }}" class="an:mt-3"><x-analytics::sparkline :values="$rateTrend" color="--an-conversion" /></div>
        </x-analytics::kpi-card>
    </div>

    {{-- Trend --}}
    <x-ui::card>
        <x-ui::section-header :title="__('Sessions et conversions au fil du temps')" class="an:mb-4" />
        @if (array_sum($trendData) > 0 || array_sum($conversionsTrend) > 0)
            <div wire:key="mkt-trend-{{ $period }}-{{ $subject }}">
                <x-analytics::area-chart :labels="$trendLabels" :data="$trendData" :label="__('Sessions')" :data2="$conversionsTrend" :label2="__('Conversions')" />
            </div>
        @else
            <div class="an:flex an:h-48 an:items-center an:justify-center an:rounded-lg an:bg-elevated an:text-[12px] an:text-muted">{{ __('Aucune session issue de pubs sur la période.') }}</div>
        @endif
    </x-ui::card>

    {{-- Campaign performance + top ads --}}
    <div class="an:grid an:grid-cols-1 an:gap-6 an:lg:grid-cols-12">
        <div class="an:lg:col-span-7">
            <div class="an:mb-4 an:flex an:items-center an:justify-between">
                <x-ui::section-header :title="__('Performance des campagnes')" />
                <a href="{{ route('analytics.admin.marketing.campaigns') }}" class="an:inline-flex an:cursor-pointer an:items-center an:gap-x-1 an:text-[12px] an:font-medium an:text-secondary an:transition-colors an:hover:text-primary">{{ __('Toutes les campagnes') }} <x-ui::icon name="arrow-right" class="an:h-3.5 an:w-3.5" /></a>
            </div>
            @if ($campaignRows === [])
                <x-ui::empty-state icon="megaphone" :title="__('Aucune campagne active sur la période')" :description="__('Le trafic taggé sera attribué ici dès qu\'une campagne correspondra.')" />
            @else
                <x-ui::table>
                    <x-ui::table.head>
                        <x-ui::table.header-cell :first="true">{{ __('Campagne') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell align="right">{{ __('Sessions') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell align="right">{{ __('Visiteurs') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell align="right">{{ __('Conv.') }}</x-ui::table.header-cell>
                        <x-ui::table.header-cell :last="true" align="right">{{ __('Taux') }}</x-ui::table.header-cell>
                    </x-ui::table.head>
                    <x-ui::table.body>
                        @foreach ($campaignRows as $row)
                            @php $showUrl = route('analytics.admin.marketing.campaigns.show', $row['id']); @endphp
                            <x-ui::table.row wire:key="perf-{{ $row['id'] }}" class="an-row-link">
                                <x-ui::table.cell :first="true" variant="primary">
                                    <a href="{{ $showUrl }}" class="an-row-link__target an:cursor-pointer an:text-[13px] an:font-medium an:text-primary an:hover:underline">{{ $row['name'] }}</a>
                                </x-ui::table.cell>
                                <x-ui::table.cell align="right" class="an:tabular-nums">{{ number_format($row['sessions'], 0, ',', ' ') }}</x-ui::table.cell>
                                <x-ui::table.cell align="right" class="an:tabular-nums">{{ number_format($row['visitors'], 0, ',', ' ') }}</x-ui::table.cell>
                                <x-ui::table.cell align="right" class="an:font-medium an:tabular-nums an:text-primary">{{ number_format($row['conversions'], 0, ',', ' ') }}</x-ui::table.cell>
                                <x-ui::table.cell :last="true" align="right" class="an:tabular-nums an:text-secondary">{{ number_format($row['rate'], 1, ',', ' ')."\u{00A0}%" }}</x-ui::table.cell>
                            </x-ui::table.row>
                        @endforeach
                    </x-ui::table.body>
                </x-ui::table>
            @endif
        </div>

        <div class="an:lg:col-span-5">
            <div class="an:mb-4 an:flex an:items-center an:justify-between">
                <x-ui::section-header :title="__('Top pubs')" />
                <a href="{{ route('analytics.admin.marketing.ads') }}" class="an:inline-flex an:cursor-pointer an:items-center an:gap-x-1 an:text-[12px] an:font-medium an:text-secondary an:transition-colors an:hover:text-primary">{{ __('Toutes les pubs') }} <x-ui::icon name="arrow-right" class="an:h-3.5 an:w-3.5" /></a>
            </div>
            <x-ui::card>
                @forelse ($adRows as $row)
                    <a href="{{ $row['campaign_id'] ? route('analytics.admin.marketing.campaigns.show', $row['campaign_id']) : '#' }}" class="an:flex an:items-center an:justify-between an:gap-4 an:py-2 {{ $row['campaign_id'] ? 'an:cursor-pointer' : '' }}" wire:key="topad-{{ $row['id'] }}">
                        <span class="an:flex an:min-w-0 an:items-center an:gap-2.5">
                            <span class="an:w-5 an:shrink-0 an:text-[11px] an:font-medium an:tabular-nums an:text-muted">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="an:min-w-0">
                                <span class="an:block an:truncate an:text-[13px] an:font-medium an:text-primary">{{ $row['name'] }}</span>
                                <span class="an:block an:truncate an:text-[11px] an:text-muted">{{ number_format($row['sessions'], 0, ',', ' ') }} {{ __('sessions') }} · {{ $row['campaign'] }}</span>
                            </span>
                        </span>
                        <span class="an:shrink-0 an:text-right an:text-[13px] an:tabular-nums"><span class="an:font-semibold an:text-primary">{{ number_format($row['conversions'], 0, ',', ' ') }}</span> <span class="an:text-[11px] an:text-muted">{{ __('conv.') }}</span></span>
                    </a>
                @empty
                    <div class="an:px-2 an:py-6 an:text-center an:text-[12px] an:text-muted">{{ __('Aucune pub avec du trafic sur la période.') }}</div>
                @endforelse
            </x-ui::card>
        </div>
    </div>

</x-analytics::root>
