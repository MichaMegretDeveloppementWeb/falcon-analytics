<x-analytics::root area="admin" class="an:space-y-6">

    <x-ui::page-header
        :title="__('Campagnes')"
        :description="$total <= 1 ? __(':count campagne', ['count' => $total]) : __(':count campagnes', ['count' => \Falcon\Analytics\Support\NumberLabel::for($total)])">
        @if ($mayCreate)
            <x-ui::button x-on:click="$anOpenWhenDone($wire.$refs.campaignForm.$wire.editCampaign(), 'an-campaign-form')"><x-ui::icon name="plus" class="an:h-4 an:w-4" /> {{ __('Nouvelle campagne') }}</x-ui::button>
        @endif
    </x-ui::page-header>

    <div class="an:w-full an:sm:max-w-xs">
        <x-ui::search-input wire:model.live.debounce.300ms="search" :placeholder="__('Rechercher une campagne…')" class="an:w-full" />
    </div>

    @if ($campaigns->isEmpty())
        <x-ui::empty-state
            icon="megaphone"
            :title="__('Aucune campagne')"
            :description="__('Créez une campagne pour commencer à mesurer vos publicités.')" />
    @else
        <x-ui::table>
            <x-ui::table.head>
                <x-ui::table.header-cell :first="true">{{ __('Campagne') }}</x-ui::table.header-cell>
                <x-ui::table.header-cell>{{ __('Plateforme') }}</x-ui::table.header-cell>
                <x-ui::table.header-cell>{{ __('Conditions') }}</x-ui::table.header-cell>
                <x-ui::table.header-cell align="right">{{ __('Publicités') }}</x-ui::table.header-cell>
                <x-ui::table.header-cell :last="true" align="right">{{ __('Actions') }}</x-ui::table.header-cell>
            </x-ui::table.head>
            <x-ui::table.body>
                @foreach ($campaigns as $campaign)
                    <x-ui::table.row
                        wire:key="campaign-{{ $campaign->id }}"
                        class="an-row-link">
                        <x-ui::table.cell :first="true" variant="primary">
                            <a href="{{ route('analytics.admin.marketing.campaigns.show', $campaign->id) }}" class="an-row-link__target an:cursor-pointer an:text-[13px] an:font-medium an:text-primary an:hover:underline">{{ $campaign->name }}</a>
                        </x-ui::table.cell>
                        <x-ui::table.cell>
                            @if ($campaign->platform !== null)<x-ui::badge color="blue">{{ $campaign->platform }}</x-ui::badge>@else<span class="an:text-muted">·</span>@endif
                        </x-ui::table.cell>
                        <x-ui::table.cell>
                            <div class="an:flex an:flex-wrap an:items-center an:gap-1.5">
                                @forelse ($campaign->conditions as $condition)
                                    <x-analytics::condition-chip :param="$condition['param']" :value="$condition['value']" />
                                @empty
                                    <span class="an:inline-flex an:items-center an:gap-1 an:text-[11px] an:text-amber-600 an:dark:text-amber-400"><x-ui::icon name="exclamation-triangle" class="an:h-3.5 an:w-3.5" /> {{ __('aucune') }}</span>
                                @endforelse
                            </div>
                        </x-ui::table.cell>
                        <x-ui::table.cell align="right" class="an:tabular-nums">{{ $campaign->adsCount }}</x-ui::table.cell>
                        <x-ui::table.cell :last="true" align="right">
                            <div class="an-row-link__above an:flex an:items-center an:justify-end an:gap-1">
                                @if ($mayEdit[$campaign->id])
                                    <x-ui::button variant="ghost" size="compact" x-on:click="$anOpenWhenDone($wire.$refs.campaignForm.$wire.editCampaign({{ $campaign->id }}), 'an-campaign-form')" aria-label="{{ __('Modifier') }}"><x-ui::icon name="pencil-square" class="an:h-3.5 an:w-3.5" /></x-ui::button>
                                @endif
                                @if ($mayDelete[$campaign->id])
                                    <x-ui::button variant="ghost" size="compact" x-on:click="$anOpenWhenDone($wire.confirmDelete({{ $campaign->id }}), 'an-campaign-delete')" aria-label="{{ __('Supprimer') }}"><x-ui::icon name="trash" class="an:h-3.5 an:w-3.5" /></x-ui::button>
                                @endif
                            </div>
                        </x-ui::table.cell>
                    </x-ui::table.row>
                @endforeach
            </x-ui::table.body>
        </x-ui::table>

        @if ($campaigns->hasPages())
            <div class="an:mt-6"><x-ui::pagination :paginator="$campaigns" mode="livewire" /></div>
        @endif
    @endif

    {{-- Campaign form --}}
    <livewire:analytics::admin.campaign-form wire:ref="campaignForm" wire:key="campaign-form" />

    {{-- Campaign deletion --}}
    <x-ui::modal name="an-campaign-delete" variant="confirm" :title="__('Supprimer la campagne ?')">
        {{ __('« :name » et toutes ses publicités et objectifs seront supprimés. Le trafic déjà capté reste en base.', ['name' => $deleteLabel]) }}

        <x-slot:actions>
            <x-ui::button type="button" variant="ghost" x-on:click="$dispatch('ui-close-modal', 'an-campaign-delete')">{{ __('Annuler') }}</x-ui::button>
            <x-ui::button type="button" variant="danger" x-on:click="$anCloseWhenDone($wire.deleteConfirmed(), 'an-campaign-delete')" :loading="true" target="deleteConfirmed">{{ __('Supprimer') }}</x-ui::button>
        </x-slot:actions>
    </x-ui::modal>
</x-analytics::root>
