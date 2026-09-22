@php
    use Falcon\Analytics\Support\NumberLabel;
@endphp

<x-analytics::root area="admin" class="an:space-y-6">

    <div>
        <a href="{{ route('analytics.admin.marketing.campaigns') }}" class="an:inline-flex an:cursor-pointer an:items-center an:gap-x-1 an:text-[12px] an:font-medium an:text-secondary an:transition-colors an:hover:text-primary">
            <x-ui::icon name="arrow-left" class="an:h-3.5 an:w-3.5" />
            {{ __('Retour aux campagnes') }}
        </a>
    </div>

    {{-- Campaign header --}}
    <div class="an:flex an:flex-wrap an:items-start an:justify-between an:gap-4">
        <div class="an:min-w-0">
            <div class="an:mb-2 an:inline-flex an:items-center an:gap-1.5 an:rounded-full an:bg-blue-50 an:px-2.5 an:py-1 an:text-[11px] an:font-semibold an:uppercase an:tracking-wide an:text-blue-700 an:dark:bg-blue-500/10 an:dark:text-blue-400">
                <x-ui::icon name="megaphone" class="an:h-3.5 an:w-3.5" /> {{ __('Campagne') }}
            </div>
            <div class="an:flex an:items-center an:gap-2">
                <h1 class="an:text-2xl an:font-semibold an:tracking-tight an:text-primary">{{ $detail->name }}</h1>
                @if ($detail->platform !== null)<x-ui::badge color="gray">{{ $detail->platform }}</x-ui::badge>@endif
            </div>
            <div class="an:mt-1.5 an:flex an:flex-wrap an:items-center an:gap-1.5">
                @forelse ($detail->conditions as $condition)
                    <x-analytics::condition-chip :param="$condition['param']" :value="$condition['value']" />
                @empty
                    <span class="an:inline-flex an:items-center an:gap-1 an:text-[11px] an:text-amber-600 an:dark:text-amber-400"><x-ui::icon name="exclamation-triangle" class="an:h-3.5 an:w-3.5" /> {{ __('Aucune condition : ne correspondra à aucun trafic') }}</span>
                @endforelse
            </div>
        </div>
        <div class="an:flex an:shrink-0 an:items-center an:gap-2">
            <x-ui::button variant="secondary" x-on:click="$anOpenWhenDone($wire.$refs.campaignForm.$wire.editCampaign({{ $detail->id }}), 'an-campaign-form')"><x-ui::icon name="pencil-square" class="an:h-4 an:w-4" /> {{ __('Modifier') }}</x-ui::button>
            <x-ui::button variant="ghost" x-on:click="$dispatch('ui-open-modal', 'an-campaign-delete')" aria-label="{{ __('Supprimer') }}"><x-ui::icon name="trash" class="an:h-4 an:w-4" /></x-ui::button>
        </div>
    </div>

    {{-- Performance (deferred content : KPIs, trend and conversions) --}}
    <div>
        <div class="an:mb-4 an:flex an:flex-wrap an:items-center an:justify-between an:gap-3">
            <x-ui::section-header :title="__('Performance')" :description="__('du :from au :to', ['from' => $range->from->isoFormat('D MMM'), 'to' => $range->to->isoFormat('D MMM YYYY')])" />
            @include('analytics::livewire.dashboard.partials.filters')
        </div>
        <livewire:analytics::admin.widgets.campaign-detail-content :period="$period" :subject="$subject" :ref-id="$detail->id" :key="'campaign-content-'.$detail->id.'-'.$period.'-'.$subject" />
    </div>

    {{-- Ads --}}
    <div>
        <div class="an:mb-4 an:flex an:items-center an:justify-between">
            <x-ui::section-header :title="__('Pubs')" :description="__('Les objectifs de conversion se définissent par pub.')" />
            <x-ui::button variant="secondary" size="compact" x-on:click="$anOpenWhenDone($wire.$refs.adForm.$wire.editAd(), 'an-ad-form')"><x-ui::icon name="plus" class="an:h-3.5 an:w-3.5" /> {{ __('Nouvelle pub') }}</x-ui::button>
        </div>

        @if ($ads === [])
            <x-ui::empty-state icon="rectangle-stack" :title="__('Aucune pub')" :description="__('Ajoutez une pub à cette campagne pour la suivre.')" />
        @else
            <x-ui::table>
                <x-ui::table.head>
                    <x-ui::table.header-cell :first="true">{{ __('Pub') }}</x-ui::table.header-cell>
                    <x-ui::table.header-cell>{{ __('Conditions') }}</x-ui::table.header-cell>
                    <x-ui::table.header-cell>{{ __('Objectifs') }}</x-ui::table.header-cell>
                    <x-ui::table.header-cell align="right">{{ __('Sessions') }}</x-ui::table.header-cell>
                    <x-ui::table.header-cell align="right">{{ __('Conv.') }}</x-ui::table.header-cell>
                    <x-ui::table.header-cell :last="true" align="right">{{ __('Actions') }}</x-ui::table.header-cell>
                </x-ui::table.head>
                <x-ui::table.body>
                    @foreach ($ads as $ad)
                        <x-ui::table.row
                            wire:key="ad-{{ $ad->id }}"
                            class="an-row-link">
                            <x-ui::table.cell :first="true" variant="primary">
                                <a href="{{ route('analytics.admin.marketing.ads.show', $ad->id) }}" class="an-row-link__target an:cursor-pointer an:text-[13px] an:font-medium an:text-primary an:hover:underline">{{ $ad->name }}</a>
                            </x-ui::table.cell>
                            <x-ui::table.cell>
                                <div class="an:flex an:flex-wrap an:items-center an:gap-1.5">
                                    @foreach ($ad->conditions as $condition)
                                        <x-analytics::condition-chip :param="$condition['param']" :value="$condition['value']" />
                                    @endforeach
                                </div>
                            </x-ui::table.cell>
                            <x-ui::table.cell>
                                <div class="an:flex an:flex-wrap an:items-center an:gap-1.5">
                                    @forelse ($ad->objectives as $objective)
                                        <x-ui::badge :color="$objective->type->value === 'funnel' ? 'blue' : 'emerald'">
                                            <x-ui::icon :name="$objective->type->value === 'funnel' ? 'funnel' : 'bolt'" class="an:h-3 an:w-3" />
                                            {{ $objective->label }}
                                        </x-ui::badge>
                                    @empty
                                        <span class="an:text-[11px] an:text-muted">{{ __('aucun') }}</span>
                                    @endforelse
                                </div>
                            </x-ui::table.cell>
                            <x-ui::table.cell align="right" class="an:tabular-nums">@if ($adMetrics === [])<span class="an:inline-block an:h-3 an:w-8 an:animate-pulse an:rounded an:bg-elevated an:align-middle"></span>@else{{ NumberLabel::for($adMetrics[$ad->id]['sessions'] ?? 0) }}@endif</x-ui::table.cell>
                            <x-ui::table.cell align="right" class="an:font-medium an:tabular-nums an:text-primary">@if ($adMetrics === [])<span class="an:inline-block an:h-3 an:w-6 an:animate-pulse an:rounded an:bg-elevated an:align-middle"></span>@else{{ NumberLabel::for($adConversions[$ad->id] ?? 0) }}@endif</x-ui::table.cell>
                            <x-ui::table.cell :last="true" align="right">
                                <div class="an-row-link__above an:flex an:items-center an:justify-end an:gap-1">
                                    <x-ui::button variant="ghost" size="compact" x-on:click="$anOpenWhenDone($wire.$refs.adForm.$wire.editAd({{ $ad->id }}), 'an-ad-form')" aria-label="{{ __('Modifier') }}"><x-ui::icon name="pencil-square" class="an:h-3.5 an:w-3.5" /></x-ui::button>
                                    <x-ui::button variant="ghost" size="compact" x-on:click="$anOpenWhenDone($wire.confirmDeleteAd({{ $ad->id }}), 'an-ad-delete')" aria-label="{{ __('Supprimer') }}"><x-ui::icon name="trash" class="an:h-3.5 an:w-3.5" /></x-ui::button>
                                </div>
                            </x-ui::table.cell>
                        </x-ui::table.row>
                    @endforeach
                </x-ui::table.body>
            </x-ui::table>
        @endif
    </div>

    {{-- Campaign form --}}
    <livewire:analytics::admin.campaign-form wire:ref="campaignForm" wire:key="campaign-form" />

    {{-- Ad form --}}
    <livewire:analytics::admin.ad-form :campaign-id="$detail->id" wire:ref="adForm" wire:key="ad-form" />

    {{-- Delete campaign --}}
    <x-ui::modal name="an-campaign-delete" variant="confirm" :title="__('Supprimer la campagne ?')">
        {{ __('« :name » et toutes ses pubs et objectifs seront supprimés. Le trafic déjà capté reste en base.', ['name' => $detail->name]) }}

        <x-slot:actions>
            <x-ui::button type="button" variant="ghost" x-on:click="$dispatch('ui-close-modal', 'an-campaign-delete')">{{ __('Annuler') }}</x-ui::button>
            <x-ui::button type="button" variant="danger" x-on:click="$anCloseWhenDone($wire.deleteCampaignConfirmed(), 'an-campaign-delete')" :loading="true" target="deleteCampaignConfirmed">{{ __('Supprimer') }}</x-ui::button>
        </x-slot:actions>
    </x-ui::modal>

    {{-- Delete ad --}}
    <x-ui::modal name="an-ad-delete" variant="confirm" :title="__('Supprimer la pub ?')">
        {{ __('« :name » et ses objectifs seront supprimés. Le trafic déjà capté reste en base.', ['name' => $deleteAdLabel]) }}

        <x-slot:actions>
            <x-ui::button type="button" variant="ghost" x-on:click="$dispatch('ui-close-modal', 'an-ad-delete')">{{ __('Annuler') }}</x-ui::button>
            <x-ui::button type="button" variant="danger" x-on:click="$anCloseWhenDone($wire.deleteAdConfirmed(), 'an-ad-delete')" :loading="true" target="deleteAdConfirmed">{{ __('Supprimer') }}</x-ui::button>
        </x-slot:actions>
    </x-ui::modal>
</x-analytics::root>
