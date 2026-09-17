<x-analytics::root area="admin" class="an:space-y-6">

    <x-ui::page-header
        :title="__('Campagnes')"
        :description="$total <= 1 ? __(':count campagne', ['count' => $total]) : __(':count campagnes', ['count' => number_format($total, 0, ',', ' ')])">
        <x-ui::button wire:click="newCampaign"><x-ui::icon name="plus" class="an:h-4 an:w-4" /> {{ __('Nouvelle campagne') }}</x-ui::button>
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
                        onclick="if (!event.target.closest('a,button')) window.location='{{ $showUrl }}'"
                        class="an:cursor-pointer">
                        <x-ui::table.cell :first="true" variant="primary">
                            <a href="{{ $showUrl }}" class="an:cursor-pointer an:text-[13px] an:font-medium an:text-primary an:hover:underline">{{ $campaign->name }}</a>
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
                            <div class="an:flex an:items-center an:justify-end an:gap-1">
                                <x-ui::button variant="ghost" size="compact" wire:click="editCampaign({{ $campaign->id }})" aria-label="{{ __('Modifier') }}"><x-ui::icon name="pencil-square" class="an:h-3.5 an:w-3.5" /></x-ui::button>
                                <x-ui::button variant="ghost" size="compact" wire:click="confirmDelete({{ $campaign->id }})" aria-label="{{ __('Supprimer') }}"><x-ui::icon name="trash" class="an:h-3.5 an:w-3.5" /></x-ui::button>
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

    {{-- Modals --}}
    <div x-on:keydown.escape.window="$wire.modal !== '' && $wire.closeModal()">

        <div x-show="$wire.modal === 'campaign'" x-cloak class="an:fixed an:inset-0 an:z-50 an:overflow-y-auto">
            <div class="an:fixed an:inset-0 an:bg-gray-900/50 an:backdrop-blur-sm an:dark:bg-black/60"></div>
            <div class="an:relative an:flex an:min-h-full an:items-center an:justify-center an:p-4" @click.self="$wire.closeModal()">
                <div class="an:w-full an:max-w-lg an:rounded-xl an:border an:border-base an:bg-surface an:p-5 an:shadow-xl">
                    <h3 class="an:text-[13px] an:font-semibold an:text-primary">{{ $campaignId ? __('Modifier la campagne') : __('Nouvelle campagne') }}</h3>
                    <div class="an:mt-4 an:space-y-4">
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
                        <div class="an:flex an:justify-end an:gap-2 an:pt-2">
                            <x-ui::button type="button" variant="ghost" wire:click="closeModal">{{ __('Annuler') }}</x-ui::button>
                            <x-ui::button type="button" wire:click="saveCampaign">{{ __('Enregistrer') }}</x-ui::button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="$wire.modal === 'delete'" x-cloak class="an:fixed an:inset-0 an:z-50 an:overflow-y-auto">
            <div class="an:fixed an:inset-0 an:bg-gray-900/50 an:backdrop-blur-sm an:dark:bg-black/60"></div>
            <div class="an:relative an:flex an:min-h-full an:items-center an:justify-center an:p-4" @click.self="$wire.closeModal()">
                <div class="an:w-full an:max-w-sm an:rounded-xl an:border an:border-base an:bg-surface an:p-5 an:shadow-xl">
                    <h3 class="an:text-[13px] an:font-semibold an:text-primary">{{ __('Supprimer la campagne ?') }}</h3>
                    <p class="an:mt-1.5 an:text-[12px] an:text-secondary">{{ __('« :name » et toutes ses pubs et objectifs seront supprimés. Le trafic déjà capté reste en base.', ['name' => $deleteLabel]) }}</p>
                    <div class="an:mt-5 an:flex an:justify-end an:gap-2">
                        <x-ui::button type="button" variant="ghost" wire:click="closeModal">{{ __('Annuler') }}</x-ui::button>
                        <x-ui::button type="button" variant="danger" wire:click="deleteConfirmed">{{ __('Supprimer') }}</x-ui::button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-analytics::root>
