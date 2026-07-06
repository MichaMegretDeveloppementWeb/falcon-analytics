@php
    $funnelLabels = [];
    foreach ($funnels as $funnel) {
        $funnelLabels[$funnel->key] = $funnel->label;
    }
@endphp

<div class="space-y-6">

    <x-ui.page-header :title="__('Publicités')" :description="__('Définissez vos campagnes, vos pubs et leurs objectifs de conversion.')">
        <x-ui.button wire:click="newCampaign"><x-ui.icon name="plus" class="h-4 w-4" /> {{ __('Nouvelle campagne') }}</x-ui.button>
    </x-ui.page-header>

    @if ($undefinedCampaigns !== [])
        <x-ui.alert variant="info">
            {{ __('Repérées dans le trafic mais pas encore définies :') }}
            <span class="font-medium">{{ implode(', ', $undefinedCampaigns) }}</span>
        </x-ui.alert>
    @endif

    @forelse ($campaigns as $campaign)
        <x-ui.card wire:key="campaign-{{ $campaign->id }}">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h3 class="text-[13px] font-semibold text-primary">{{ $campaign->name }}</h3>
                        @if ($campaign->platform)<x-ui.badge color="blue">{{ $campaign->platform }}</x-ui.badge>@endif
                    </div>
                    <p class="mt-0.5 text-[12px] text-muted">campaign = <span class="font-mono">{{ $campaign->key }}</span></p>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <x-ui.button variant="ghost" size="compact" wire:click="newAd({{ $campaign->id }})"><x-ui.icon name="plus" class="h-3.5 w-3.5" /> {{ __('Pub') }}</x-ui.button>
                    <x-ui.button variant="ghost" size="compact" wire:click="editCampaign({{ $campaign->id }})" aria-label="{{ __('Modifier') }}"><x-ui.icon name="pencil-square" class="h-3.5 w-3.5" /></x-ui.button>
                    <x-ui.button variant="ghost" size="compact" wire:click="confirmDelete('campaign', {{ $campaign->id }})" aria-label="{{ __('Supprimer') }}"><x-ui.icon name="trash" class="h-3.5 w-3.5" /></x-ui.button>
                </div>
            </div>

            @if ($campaign->ads->isNotEmpty())
                <div class="mt-4 space-y-2">
                    @foreach ($campaign->ads as $ad)
                        <div wire:key="ad-{{ $ad->id }}" class="flex items-start justify-between gap-3 rounded-lg border border-subtle bg-elevated px-4 py-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-[13px] font-medium text-primary">{{ $ad->name }}</span>
                                    <span class="text-[11px] text-muted">ad = <span class="font-mono">{{ $ad->key }}</span></span>
                                </div>
                                <div class="mt-1.5 flex flex-wrap gap-1">
                                    @forelse ($ad->objectives as $obj)
                                        <x-ui.badge color="gray">
                                            @if ($obj->type->value === 'funnel')
                                                <x-ui.icon name="funnel" class="h-3 w-3" /> {{ $funnelLabels[$obj->reference] ?? $obj->reference }}
                                            @else
                                                <x-ui.icon name="bolt" class="h-3 w-3" /> {{ $obj->reference }}
                                            @endif
                                        </x-ui.badge>
                                    @empty
                                        <span class="text-[11px] text-muted">{{ __('Aucun objectif de conversion') }}</span>
                                    @endforelse
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <x-ui.button variant="ghost" size="compact" wire:click="editAd({{ $ad->id }})" aria-label="{{ __('Modifier') }}"><x-ui.icon name="pencil-square" class="h-3.5 w-3.5" /></x-ui.button>
                                <x-ui.button variant="ghost" size="compact" wire:click="confirmDelete('ad', {{ $ad->id }})" aria-label="{{ __('Supprimer') }}"><x-ui.icon name="trash" class="h-3.5 w-3.5" /></x-ui.button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mt-4 text-[12px] text-muted">{{ __('Aucune pub dans cette campagne.') }}</p>
            @endif
        </x-ui.card>
    @empty
        <x-ui.empty-state icon="megaphone" :title="__('Aucune campagne')" :description="__('Créez une campagne pour commencer à suivre vos pubs.')" />
    @endforelse

    {{-- Modals : visibilité pilotée par l'état Livewire (robuste, sans teleport) --}}
    <div x-on:keydown.escape.window="$wire.modal !== '' && $wire.closeModal()">

        {{-- Campaign --}}
        <div x-show="$wire.modal === 'campaign'" x-cloak class="fixed inset-0 z-50">
            <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm dark:bg-black/60"></div>
            <div class="relative flex min-h-full items-center justify-center p-4" @click.self="$wire.closeModal()">
                <div class="w-full max-w-lg rounded-xl border border-base bg-surface p-5 shadow-xl">
                    <h3 class="text-[13px] font-semibold text-primary">{{ $campaignId ? __('Modifier la campagne') : __('Nouvelle campagne') }}</h3>
                    <form wire:submit="saveCampaign" class="mt-4 space-y-4">
                        <x-ui.form-group :label="__('Nom')" for="campaignName">
                            <x-ui.input wire:model="campaignName" id="campaignName" :error="$errors->has('campaignName')" />
                        </x-ui.form-group>
                        <x-ui.form-group :label="__('Identifiant dans l\'URL')" for="campaignKey" :hint="__('La valeur du paramètre campagne, ex. ete')">
                            <x-ui.input wire:model="campaignKey" id="campaignKey" :error="$errors->has('campaignKey')" />
                        </x-ui.form-group>
                        <x-ui.form-group :label="__('Plateforme')" for="campaignPlatform" :hint="__('Optionnel : Meta, Google...')">
                            <x-ui.input wire:model="campaignPlatform" id="campaignPlatform" :error="$errors->has('campaignPlatform')" />
                        </x-ui.form-group>
                        <div class="flex justify-end gap-2 pt-2">
                            <x-ui.button type="button" variant="ghost" wire:click="closeModal">{{ __('Annuler') }}</x-ui.button>
                            <x-ui.button type="submit">{{ __('Enregistrer') }}</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Ad + objectives --}}
        <div x-show="$wire.modal === 'ad'" x-cloak class="fixed inset-0 z-50">
            <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm dark:bg-black/60"></div>
            <div class="relative flex min-h-full items-center justify-center p-4" @click.self="$wire.closeModal()">
                <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-base bg-surface p-5 shadow-xl">
                    <h3 class="text-[13px] font-semibold text-primary">{{ $adId ? __('Modifier la pub') : __('Nouvelle pub') }}</h3>

                    <div class="mt-4 space-y-4">
                        <x-ui.form-group :label="__('Nom')" for="adName">
                            <x-ui.input wire:model="adName" id="adName" :error="$errors->has('adName')" />
                        </x-ui.form-group>
                        <x-ui.form-group :label="__('Identifiant dans l\'URL')" for="adKey" :hint="__('La valeur du paramètre pub, ex. cabrio')">
                            <x-ui.input wire:model="adKey" id="adKey" :error="$errors->has('adKey')" />
                        </x-ui.form-group>
                        <div class="flex justify-end">
                            <x-ui.button wire:click="saveAd">{{ $adId ? __('Enregistrer') : __('Créer la pub') }}</x-ui.button>
                        </div>
                    </div>

                    @if ($editingAd)
                        <div class="mt-5 border-t border-subtle pt-4">
                            <h4 class="text-[12px] font-semibold text-primary">{{ __('Objectifs de conversion') }}</h4>
                            <p class="mt-0.5 text-[12px] text-secondary">{{ __('Cette pub ne sera créditée que des conversions ci-dessous.') }}</p>

                            @if ($editingAd->objectives->isNotEmpty())
                                <div class="mt-3 space-y-1.5">
                                    @foreach ($editingAd->objectives as $obj)
                                        <div wire:key="obj-{{ $obj->id }}" class="flex items-center justify-between gap-2 rounded-lg bg-elevated px-3 py-2">
                                            <span class="min-w-0 truncate text-[12px] text-secondary">
                                                @if ($obj->type->value === 'funnel')
                                                    <x-ui.icon name="funnel" class="inline h-3.5 w-3.5" /> {{ __('Entonnoir') }}{{ "\u{00A0}" }}: {{ $funnelLabels[$obj->reference] ?? $obj->reference }}
                                                @else
                                                    <x-ui.icon name="bolt" class="inline h-3.5 w-3.5" /> {{ __('Événement') }}{{ "\u{00A0}" }}: <span class="font-mono">{{ $obj->reference }}</span> · {{ (float) $obj->value }} {{ __('pts') }}
                                                @endif
                                            </span>
                                            <button type="button" wire:click="removeObjective({{ $obj->id }})" class="shrink-0 cursor-pointer text-muted transition-colors hover:text-red-600" aria-label="{{ __('Retirer') }}"><x-ui.icon name="x-mark" class="h-4 w-4" /></button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="mt-3 space-y-3 rounded-lg border border-subtle p-3">
                                <x-ui.form-group :label="__('Type')" for="objType">
                                    <x-ui.select wire:model.live="objType" id="objType" :options="['funnel' => __('Entonnoir'), 'event' => __('Événement')]" />
                                </x-ui.form-group>

                                @if ($objType === 'funnel')
                                    <x-ui.form-group :label="__('Entonnoir')" for="objReference">
                                        <x-ui.select wire:model="objReference" id="objReference" :options="$funnelLabels" :placeholder="__('Choisir un entonnoir...')" :error="$errors->has('objReference')" />
                                    </x-ui.form-group>
                                @else
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <x-ui.form-group :label="__('Événement')" for="objReference" :hint="__('Nom exact de l\'event')">
                                            <x-ui.input wire:model="objReference" id="objReference" list="observed-events" placeholder="Lead" :error="$errors->has('objReference')" />
                                            <datalist id="observed-events">
                                                @foreach ($observedEvents as $eventName)
                                                    <option value="{{ $eventName }}"></option>
                                                @endforeach
                                            </datalist>
                                        </x-ui.form-group>
                                        <x-ui.form-group :label="__('Valeur (points)')" for="objValue">
                                            <x-ui.input type="number" step="0.01" min="0" wire:model="objValue" id="objValue" :error="$errors->has('objValue')" />
                                        </x-ui.form-group>
                                    </div>
                                @endif

                                <x-ui.button type="button" variant="secondary" size="compact" wire:click="addObjective"><x-ui.icon name="plus" class="h-3.5 w-3.5" /> {{ __('Ajouter l\'objectif') }}</x-ui.button>
                            </div>
                        </div>
                    @endif

                    <div class="mt-5 flex justify-end border-t border-subtle pt-4">
                        <x-ui.button type="button" variant="ghost" wire:click="closeModal">{{ __('Fermer') }}</x-ui.button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Delete confirmation --}}
        <div x-show="$wire.modal === 'delete'" x-cloak class="fixed inset-0 z-50">
            <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm dark:bg-black/60"></div>
            <div class="relative flex min-h-full items-center justify-center p-4" @click.self="$wire.closeModal()">
                <div class="w-full max-w-sm rounded-xl border border-base bg-surface p-5 shadow-xl">
                    <h3 class="text-[13px] font-semibold text-primary">{{ $deleteType === 'campaign' ? __('Supprimer la campagne ?') : __('Supprimer la pub ?') }}</h3>
                    <p class="mt-1.5 text-[12px] text-secondary">
                        @if ($deleteType === 'campaign')
                            {{ __('« :name » et toutes ses pubs et objectifs seront supprimés. Le trafic déjà capté reste en base.', ['name' => $deleteLabel]) }}
                        @else
                            {{ __('« :name » et ses objectifs seront supprimés. Le trafic déjà capté reste en base.', ['name' => $deleteLabel]) }}
                        @endif
                    </p>
                    <div class="mt-5 flex justify-end gap-2">
                        <x-ui.button type="button" variant="ghost" wire:click="closeModal">{{ __('Annuler') }}</x-ui.button>
                        <x-ui.button type="button" variant="danger" wire:click="deleteConfirmed">{{ __('Supprimer') }}</x-ui.button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
