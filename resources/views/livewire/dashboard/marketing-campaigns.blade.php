<x-analytics::root area="admin" class="an:space-y-6">

    <x-ui::page-header
        :title="__('Campagnes')"
        :description="$total <= 1 ? __(':count campagne', ['count' => $total]) : __(':count campagnes', ['count' => number_format($total, 0, ',', ' ')])">
        <x-ui::button x-on:click="$anOpenWhenDone($wire.newCampaign(), 'an-campaign-form')"><x-ui::icon name="plus" class="an:h-4 an:w-4" /> {{ __('Nouvelle campagne') }}</x-ui::button>
    </x-ui::page-header>

    <div class="an:w-full an:sm:max-w-xs">
        <x-ui::search-input wire:model.live.debounce.300ms="search" :placeholder="__('Rechercher une campagne...')" class="an:w-full" />
    </div>

    @if ($campaigns->isEmpty())
        <x-ui::empty-state
            icon="megaphone"
            :title="__('Aucune campagne')"
            :description="__('Créez une campagne pour commencer à mesurer vos pubs.')" />
    @else
        <x-ui::table>
            <x-ui::table.head>
                <x-ui::table.header-cell :first="true">{{ __('Campagne') }}</x-ui::table.header-cell>
                <x-ui::table.header-cell>{{ __('Plateforme') }}</x-ui::table.header-cell>
                <x-ui::table.header-cell>{{ __('Conditions') }}</x-ui::table.header-cell>
                <x-ui::table.header-cell align="right">{{ __('Pubs') }}</x-ui::table.header-cell>
                <x-ui::table.header-cell :last="true" align="right">{{ __('Actions') }}</x-ui::table.header-cell>
            </x-ui::table.head>
            <x-ui::table.body>
                @foreach ($campaigns as $campaign)
                    @php $showUrl = route('analytics.admin.marketing.campaigns.show', $campaign); @endphp
                    <x-ui::table.row
                        wire:key="campaign-{{ $campaign->id }}"
                        class="an-row-link">
                        <x-ui::table.cell :first="true" variant="primary">
                            <a href="{{ $showUrl }}" class="an-row-link__target an:cursor-pointer an:text-[13px] an:font-medium an:text-primary an:hover:underline">{{ $campaign->name }}</a>
                        </x-ui::table.cell>
                        <x-ui::table.cell>
                            @if ($campaign->platform)<x-ui::badge color="blue">{{ $campaign->platform }}</x-ui::badge>@else<span class="an:text-muted">·</span>@endif
                        </x-ui::table.cell>
                        <x-ui::table.cell>
                            <div class="an:flex an:flex-wrap an:items-center an:gap-1.5">
                                @forelse ($campaign->match_conditions ?? [] as $condition)
                                    <x-analytics::condition-chip :param="$condition['param']" :value="$condition['value']" />
                                @empty
                                    <span class="an:inline-flex an:items-center an:gap-1 an:text-[11px] an:text-amber-600 an:dark:text-amber-400"><x-ui::icon name="exclamation-triangle" class="an:h-3.5 an:w-3.5" /> {{ __('aucune') }}</span>
                                @endforelse
                            </div>
                        </x-ui::table.cell>
                        <x-ui::table.cell align="right" class="an:tabular-nums">{{ $campaign->ads_count }}</x-ui::table.cell>
                        <x-ui::table.cell :last="true" align="right">
                            <div class="an-row-link__above an:flex an:items-center an:justify-end an:gap-1">
                                <x-ui::button variant="ghost" size="compact" x-on:click="$anOpenWhenDone($wire.editCampaign({{ $campaign->id }}), 'an-campaign-form')" aria-label="{{ __('Modifier') }}"><x-ui::icon name="pencil-square" class="an:h-3.5 an:w-3.5" /></x-ui::button>
                                <x-ui::button variant="ghost" size="compact" x-on:click="$anOpenWhenDone($wire.confirmDelete({{ $campaign->id }}), 'an-campaign-delete')" aria-label="{{ __('Supprimer') }}"><x-ui::icon name="trash" class="an:h-3.5 an:w-3.5" /></x-ui::button>
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
    <x-ui::modal name="an-campaign-form" :title="$campaignId ? __('Modifier la campagne') : __('Nouvelle campagne')">
        <div class="an:space-y-4">
            <x-ui::form-group :label="__('Nom')" for="campaignName">
                <x-ui::input wire:model="campaignName" id="campaignName" placeholder="{{ __('Ex. Été 2026') }}" :error="$errors->has('campaignName')" />
            </x-ui::form-group>
            <x-ui::form-group :label="__('Plateforme')" for="campaignPlatform" :hint="__('Optionnel : Meta, Google...')">
                <x-ui::input wire:model="campaignPlatform" id="campaignPlatform" :error="$errors->has('campaignPlatform')" />
            </x-ui::form-group>
            <x-ui::form-group :label="__('Conditions d\'URL')" :hint="__('La campagne correspond si TOUS ces paramètres sont présents dans l\'URL de la visite.')" :error="$errors->first('campaignConditions.*') ?: $errors->first('campaignConditions')">
                <div class="an:space-y-2">
                    @foreach ($campaignConditions as $index => $condition)
                        <div wire:key="cc-{{ $index }}" class="an:flex an:items-center an:gap-2">
                            <x-ui::input wire:model="campaignConditions.{{ $index }}.param" placeholder="{{ __('paramètre') }}" class="an:flex-1" :error="$errors->has('campaignConditions.'.$index.'.param')" />
                            <span class="an:text-muted">=</span>
                            <x-ui::input wire:model="campaignConditions.{{ $index }}.value" placeholder="{{ __('valeur') }}" class="an:flex-1" :error="$errors->has('campaignConditions.'.$index.'.value')" />
                            <button type="button" wire:click="removeCampaignCondition({{ $index }})" @class(['an:shrink-0 an:cursor-pointer an:text-muted an:transition-colors an:hover:text-red-600', 'an:pointer-events-none an:opacity-30' => count($campaignConditions) <= 1]) aria-label="{{ __('Retirer') }}"><x-ui::icon name="x-mark" class="an:h-4 an:w-4" /></button>
                        </div>
                    @endforeach
                </div>
                <x-ui::button type="button" variant="ghost" size="compact" wire:click="addCampaignCondition" class="an:mt-2"><x-ui::icon name="plus" class="an:h-3.5 an:w-3.5" /> {{ __('Ajouter une condition') }}</x-ui::button>
            </x-ui::form-group>
        </div>

        <x-slot:footer>
            <x-ui::button type="button" variant="ghost" x-on:click="$dispatch('ui-close-modal', 'an-campaign-form')">{{ __('Annuler') }}</x-ui::button>
            <x-ui::button type="button" x-on:click="$anCloseWhenDone($wire.saveCampaign(), 'an-campaign-form')">{{ __('Enregistrer') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::modal>

    {{-- Campaign deletion --}}
    <x-ui::modal name="an-campaign-delete" variant="confirm" :title="__('Supprimer la campagne ?')">
        {{ __('« :name » et toutes ses pubs et objectifs seront supprimés. Le trafic déjà capté reste en base.', ['name' => $deleteLabel]) }}

        <x-slot:actions>
            <x-ui::button type="button" variant="ghost" x-on:click="$dispatch('ui-close-modal', 'an-campaign-delete')">{{ __('Annuler') }}</x-ui::button>
            <x-ui::button type="button" variant="danger" x-on:click="$anCloseWhenDone($wire.deleteConfirmed(), 'an-campaign-delete')">{{ __('Supprimer') }}</x-ui::button>
        </x-slot:actions>
    </x-ui::modal>
</x-analytics::root>
