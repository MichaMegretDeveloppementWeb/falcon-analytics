@php
    $routeName = config('analytics.marketing.route_name', 'marketing');
@endphp

<div class="space-y-6">

    <x-ui.page-header
        :title="__('Pubs')"
        :description="$total <= 1 ? __(':count pub', ['count' => $total]) : __(':count pubs', ['count' => number_format($total, 0, ',', ' ')])" />

    <div class="w-full sm:max-w-xs">
        <x-ui.search-input wire:model.live.debounce.300ms="search" :placeholder="__('Rechercher une pub ou campagne...')" class="w-full" />
    </div>

    @if ($ads->isEmpty())
        <x-ui.empty-state
            icon="rectangle-stack"
            :title="__('Aucune pub')"
            :description="__('Ajoutez des pubs depuis le détail d\'une campagne.')" />
    @else
        <x-ui.table>
            <x-ui.table.head>
                <x-ui.table.header-cell :first="true">{{ __('Pub') }}</x-ui.table.header-cell>
                <x-ui.table.header-cell>{{ __('Campagne') }}</x-ui.table.header-cell>
                <x-ui.table.header-cell>{{ __('Conditions') }}</x-ui.table.header-cell>
                <x-ui.table.header-cell :last="true">{{ __('Objectifs') }}</x-ui.table.header-cell>
            </x-ui.table.head>
            <x-ui.table.body>
                @foreach ($ads as $ad)
                    @php $showUrl = route($routeName.'.campaigns.show', $ad->campaign_id); @endphp
                    <x-ui.table.row
                        wire:key="ad-{{ $ad->id }}"
                        onclick="if (!event.target.closest('a')) window.location='{{ $showUrl }}'"
                        class="cursor-pointer">
                        <x-ui.table.cell :first="true" variant="primary">{{ $ad->name }}</x-ui.table.cell>
                        <x-ui.table.cell>
                            <a href="{{ $showUrl }}" class="cursor-pointer text-[13px] text-secondary hover:text-primary hover:underline">{{ $ad->campaign->name }}</a>
                        </x-ui.table.cell>
                        <x-ui.table.cell>
                            <div class="flex flex-wrap items-center gap-1.5">
                                @foreach ($ad->match_conditions ?? [] as $condition)
                                    <x-analytics::condition-chip :param="$condition['param']" :value="$condition['value']" />
                                @endforeach
                            </div>
                        </x-ui.table.cell>
                        <x-ui.table.cell :last="true">
                            <div class="flex flex-wrap items-center gap-1.5">
                                @forelse ($ad->objectives as $objective)
                                    <x-ui.badge :color="$objective->type->value === 'funnel' ? 'blue' : 'emerald'">
                                        <x-ui.icon :name="$objective->type->value === 'funnel' ? 'funnel' : 'bolt'" class="h-3 w-3" />
                                        {{ $objectiveLabels[$objective->type->value.':'.$objective->reference] ?? $objective->reference }}
                                    </x-ui.badge>
                                @empty
                                    <span class="text-[11px] text-muted">{{ __('aucun') }}</span>
                                @endforelse
                            </div>
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @endforeach
            </x-ui.table.body>
        </x-ui.table>

        @if ($ads->hasPages())
            <div class="mt-6"><x-ui.pagination :paginator="$ads" mode="livewire" /></div>
        @endif
    @endif

</div>
