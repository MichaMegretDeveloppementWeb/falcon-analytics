@php
    $routeName = config('analytics.marketing.route_name', 'marketing');
@endphp

<div class="space-y-6">

    <x-ui.page-header
        :title="__('Campagnes')"
        :description="$total <= 1 ? __(':count campagne', ['count' => $total]) : __(':count campagnes', ['count' => number_format($total, 0, ',', ' ')])">
        <x-ui.button wire:click="newCampaign"><x-ui.icon name="plus" class="h-4 w-4" /> {{ __('Nouvelle campagne') }}</x-ui.button>
    </x-ui.page-header>

    <div class="w-full sm:max-w-xs">
        <x-ui.search-input wire:model.live.debounce.300ms="search" :placeholder="__('Rechercher une campagne...')" class="w-full" />
    </div>

    @if ($campaigns->isEmpty())
        <x-ui.empty-state
            icon="megaphone"
            :title="__('Aucune campagne')"
            :description="__('Créez une campagne pour commencer à mesurer vos pubs.')" />
    @else
        <x-ui.table>
            <x-ui.table.head>
                <x-ui.table.header-cell :first="true">{{ __('Campagne') }}</x-ui.table.header-cell>
                <x-ui.table.header-cell>{{ __('Plateforme') }}</x-ui.table.header-cell>
                <x-ui.table.header-cell>{{ __('Conditions') }}</x-ui.table.header-cell>
                <x-ui.table.header-cell align="right">{{ __('Pubs') }}</x-ui.table.header-cell>
                <x-ui.table.header-cell :last="true" align="right">{{ __('Actions') }}</x-ui.table.header-cell>
            </x-ui.table.head>
            <x-ui.table.body>
                @foreach ($campaigns as $campaign)
                    @php $showUrl = route($routeName.'.campaigns.show', $campaign); @endphp
                    <x-ui.table.row
                        wire:key="campaign-{{ $campaign->id }}"
                        onclick="if (!event.target.closest('a,button')) window.location='{{ $showUrl }}'"
                        class="cursor-pointer">
                        <x-ui.table.cell :first="true" variant="primary">
                            <a href="{{ $showUrl }}" class="cursor-pointer text-[13px] font-medium text-primary hover:underline">{{ $campaign->name }}</a>
                        </x-ui.table.cell>
                        <x-ui.table.cell>
                            @if ($campaign->platform)<x-ui.badge color="blue">{{ $campaign->platform }}</x-ui.badge>@else<span class="text-muted">·</span>@endif
                        </x-ui.table.cell>
                        <x-ui.table.cell>
                            <div class="flex flex-wrap items-center gap-1.5">
                                @forelse ($campaign->match_conditions ?? [] as $condition)
                                    <x-analytics::condition-chip :param="$condition['param']" :value="$condition['value']" />
                                @empty
                                    <span class="inline-flex items-center gap-1 text-[11px] text-amber-600 dark:text-amber-400"><x-ui.icon name="exclamation-triangle" class="h-3.5 w-3.5" /> {{ __('aucune') }}</span>
                                @endforelse
                            </div>
                        </x-ui.table.cell>
                        <x-ui.table.cell align="right" class="tabular-nums">{{ $campaign->ads_count }}</x-ui.table.cell>
                        <x-ui.table.cell :last="true" align="right">
                            <div class="flex items-center justify-end gap-1">
                                <x-ui.button variant="ghost" size="compact" wire:click="editCampaign({{ $campaign->id }})" aria-label="{{ __('Modifier') }}"><x-ui.icon name="pencil-square" class="h-3.5 w-3.5" /></x-ui.button>
                                <x-ui.button variant="ghost" size="compact" wire:click="confirmDelete({{ $campaign->id }})" aria-label="{{ __('Supprimer') }}"><x-ui.icon name="trash" class="h-3.5 w-3.5" /></x-ui.button>
                            </div>
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @endforeach
            </x-ui.table.body>
        </x-ui.table>

        @if ($campaigns->hasPages())
            <div class="mt-6"><x-ui.pagination :paginator="$campaigns" mode="livewire" /></div>
        @endif
    @endif

    {{-- Modals --}}
    <div x-on:keydown.escape.window="$wire.modal !== '' && $wire.closeModal()">

        <div x-show="$wire.modal === 'campaign'" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm dark:bg-black/60"></div>
            <div class="relative flex min-h-full items-center justify-center p-4" @click.self="$wire.closeModal()">
                <div class="w-full max-w-lg rounded-xl border border-base bg-surface p-5 shadow-xl">
                    <h3 class="text-[13px] font-semibold text-primary">{{ $campaignId ? __('Modifier la campagne') : __('Nouvelle campagne') }}</h3>
                    <div class="mt-4 space-y-4">
                        <x-ui.form-group :label="__('Nom')" for="campaignName">
                            <x-ui.input wire:model="campaignName" id="campaignName" placeholder="{{ __('Ex. Été 2026') }}" :error="$errors->has('campaignName')" />
                        </x-ui.form-group>
                        <x-ui.form-group :label="__('Plateforme')" for="campaignPlatform" :hint="__('Optionnel : Meta, Google...')">
                            <x-ui.input wire:model="campaignPlatform" id="campaignPlatform" :error="$errors->has('campaignPlatform')" />
                        </x-ui.form-group>
                        <x-ui.form-group :label="__('Conditions d\'URL')" :hint="__('La campagne correspond si TOUS ces paramètres sont présents dans l\'URL de la visite.')" :error="$errors->first('campaignConditions.*') ?: $errors->first('campaignConditions')">
                            <div class="space-y-2">
                                @foreach ($campaignConditions as $index => $condition)
                                    <div wire:key="cc-{{ $index }}" class="flex items-center gap-2">
                                        <x-ui.input wire:model="campaignConditions.{{ $index }}.param" placeholder="{{ __('paramètre') }}" class="flex-1" :error="$errors->has('campaignConditions.'.$index.'.param')" />
                                        <span class="text-muted">=</span>
                                        <x-ui.input wire:model="campaignConditions.{{ $index }}.value" placeholder="{{ __('valeur') }}" class="flex-1" :error="$errors->has('campaignConditions.'.$index.'.value')" />
                                        <button type="button" wire:click="removeCondition({{ $index }})" @class(['shrink-0 cursor-pointer text-muted transition-colors hover:text-red-600', 'pointer-events-none opacity-30' => count($campaignConditions) <= 1]) aria-label="{{ __('Retirer') }}"><x-ui.icon name="x-mark" class="h-4 w-4" /></button>
                                    </div>
                                @endforeach
                            </div>
                            <x-ui.button type="button" variant="ghost" size="compact" wire:click="addCondition" class="mt-2"><x-ui.icon name="plus" class="h-3.5 w-3.5" /> {{ __('Ajouter une condition') }}</x-ui.button>
                        </x-ui.form-group>
                        <div class="flex justify-end gap-2 pt-2">
                            <x-ui.button type="button" variant="ghost" wire:click="closeModal">{{ __('Annuler') }}</x-ui.button>
                            <x-ui.button type="button" wire:click="saveCampaign">{{ __('Enregistrer') }}</x-ui.button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="$wire.modal === 'delete'" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm dark:bg-black/60"></div>
            <div class="relative flex min-h-full items-center justify-center p-4" @click.self="$wire.closeModal()">
                <div class="w-full max-w-sm rounded-xl border border-base bg-surface p-5 shadow-xl">
                    <h3 class="text-[13px] font-semibold text-primary">{{ __('Supprimer la campagne ?') }}</h3>
                    <p class="mt-1.5 text-[12px] text-secondary">{{ __('« :name » et toutes ses pubs et objectifs seront supprimés. Le trafic déjà capté reste en base.', ['name' => $deleteLabel]) }}</p>
                    <div class="mt-5 flex justify-end gap-2">
                        <x-ui.button type="button" variant="ghost" wire:click="closeModal">{{ __('Annuler') }}</x-ui.button>
                        <x-ui.button type="button" variant="danger" wire:click="deleteConfirmed">{{ __('Supprimer') }}</x-ui.button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
