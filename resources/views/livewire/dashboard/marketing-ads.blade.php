<x-analytics::root area="admin" class="an:space-y-6">

    <x-ui::page-header
        :title="__('Pubs')"
        :description="$total <= 1 ? __(':count pub', ['count' => $total]) : __(':count pubs', ['count' => number_format($total, 0, ',', ' ')])" />

    <div class="an:w-full an:sm:max-w-xs">
        <x-ui::search-input wire:model.live.debounce.300ms="search" :placeholder="__('Rechercher une pub ou campagne...')" class="an:w-full" />
    </div>

    @if ($ads->isEmpty())
        <x-ui::empty-state
            icon="rectangle-stack"
            :title="__('Aucune pub')"
            :description="__('Ajoutez des pubs depuis le détail d\'une campagne.')" />
    @else
        <x-ui::table>
            <x-ui::table.head>
                <x-ui::table.header-cell :first="true">{{ __('Pub') }}</x-ui::table.header-cell>
                <x-ui::table.header-cell>{{ __('Campagne') }}</x-ui::table.header-cell>
                <x-ui::table.header-cell>{{ __('Conditions') }}</x-ui::table.header-cell>
                <x-ui::table.header-cell :last="true">{{ __('Objectifs') }}</x-ui::table.header-cell>
            </x-ui::table.head>
            <x-ui::table.body>
                @foreach ($ads as $ad)
                    @php
                        $adUrl = route('analytics.admin.marketing.ads.show', $ad->id);
                        $campaignUrl = route('analytics.admin.marketing.campaigns.show', $ad->campaign_id);
                    @endphp
                    <x-ui::table.row
                        wire:key="ad-{{ $ad->id }}"
                        class="an-row-link">
                        <x-ui::table.cell :first="true" variant="primary">
                            <a href="{{ $adUrl }}" class="an-row-link__target an:cursor-pointer an:text-[13px] an:font-medium an:text-primary an:hover:underline">{{ $ad->name }}</a>
                        </x-ui::table.cell>
                        <x-ui::table.cell>
                            <a href="{{ $campaignUrl }}" class="an-row-link__above an:cursor-pointer an:text-[13px] an:text-secondary an:hover:text-primary an:hover:underline">{{ $ad->campaign->name }}</a>
                        </x-ui::table.cell>
                        <x-ui::table.cell>
                            <div class="an:flex an:flex-wrap an:items-center an:gap-1.5">
                                @foreach ($ad->match_conditions ?? [] as $condition)
                                    <x-analytics::condition-chip :param="$condition['param']" :value="$condition['value']" />
                                @endforeach
                            </div>
                        </x-ui::table.cell>
                        <x-ui::table.cell :last="true">
                            <div class="an:flex an:flex-wrap an:items-center an:gap-1.5">
                                @forelse ($ad->objectives as $objective)
                                    <x-ui::badge :color="$objective->type->value === 'funnel' ? 'blue' : 'emerald'">
                                        <x-ui::icon :name="$objective->type->value === 'funnel' ? 'funnel' : 'bolt'" class="an:h-3 an:w-3" />
                                        {{ $objectiveLabels[$objective->type->value.':'.$objective->reference] ?? $objective->reference }}
                                    </x-ui::badge>
                                @empty
                                    <span class="an:text-[11px] an:text-muted">{{ __('aucun') }}</span>
                                @endforelse
                            </div>
                        </x-ui::table.cell>
                    </x-ui::table.row>
                @endforeach
            </x-ui::table.body>
        </x-ui::table>

        @if ($ads->hasPages())
            <div class="an:mt-6"><x-ui::pagination :paginator="$ads" mode="livewire" /></div>
        @endif
    @endif

</x-analytics::root>
