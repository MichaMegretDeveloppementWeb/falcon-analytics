@php
    use Illuminate\Support\Str;

    $objectiveLabels = [];
    foreach ($funnelOptions as $option) {
        $objectiveLabels['funnel:'.$option['reference']] = $option['label'];
    }
    foreach ($eventOptions as $option) {
        $objectiveLabels['event:'.$option['reference']] = $option['label'];
    }

    $selectedObjectiveLabel = $objReference !== '' ? ($objectiveLabels[$objType.':'.$objReference] ?? $objReference) : '';
@endphp

<div class="space-y-8">

    <x-ui.page-header :title="__('Marketing')" :description="__('Vos campagnes publicitaires, leurs pubs et leurs objectifs de conversion.')">
        <x-ui.button wire:click="newCampaign"><x-ui.icon name="plus" class="h-4 w-4" /> {{ __('Nouvelle campagne') }}</x-ui.button>
    </x-ui.page-header>

    @forelse ($campaigns as $campaign)
        <x-ui.card wire:key="campaign-{{ $campaign->id }}">
            {{-- Campaign header --}}
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h2 class="text-[13px] font-semibold text-primary">{{ $campaign->name }}</h2>
                        @if ($campaign->platform)<x-ui.badge color="blue">{{ $campaign->platform }}</x-ui.badge>@endif
                    </div>
                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                        @forelse ($campaign->match_conditions ?? [] as $condition)
                            <x-analytics::condition-chip :param="$condition['param']" :value="$condition['value']" />
                        @empty
                            <span class="inline-flex items-center gap-1 text-[11px] text-amber-600 dark:text-amber-400"><x-ui.icon name="exclamation-triangle" class="h-3.5 w-3.5" /> {{ __('Aucune condition : ne correspondra à aucun trafic') }}</span>
                        @endforelse
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <x-ui.button variant="secondary" size="compact" wire:click="newAd({{ $campaign->id }})"><x-ui.icon name="plus" class="h-3.5 w-3.5" /> {{ __('Pub') }}</x-ui.button>
                    <x-ui.button variant="ghost" size="compact" wire:click="editCampaign({{ $campaign->id }})" aria-label="{{ __('Modifier') }}"><x-ui.icon name="pencil-square" class="h-3.5 w-3.5" /></x-ui.button>
                    <x-ui.button variant="ghost" size="compact" wire:click="confirmDelete('campaign', {{ $campaign->id }})" aria-label="{{ __('Supprimer') }}"><x-ui.icon name="trash" class="h-3.5 w-3.5" /></x-ui.button>
                </div>
            </div>

            {{-- Ads --}}
            @if ($campaign->ads->isNotEmpty())
                <div class="mt-5 space-y-2.5">
                    @foreach ($campaign->ads as $ad)
                        <div wire:key="ad-{{ $ad->id }}" class="rounded-lg border border-subtle bg-elevated px-4 py-3">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <span class="text-[13px] font-medium text-primary">{{ $ad->name }}</span>
                                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                        @foreach ($ad->match_conditions ?? [] as $condition)
                                            <x-analytics::condition-chip :param="$condition['param']" :value="$condition['value']" />
                                        @endforeach
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    <x-ui.button variant="ghost" size="compact" wire:click="editAd({{ $ad->id }})" aria-label="{{ __('Modifier') }}"><x-ui.icon name="pencil-square" class="h-3.5 w-3.5" /></x-ui.button>
                                    <x-ui.button variant="ghost" size="compact" wire:click="confirmDelete('ad', {{ $ad->id }})" aria-label="{{ __('Supprimer') }}"><x-ui.icon name="trash" class="h-3.5 w-3.5" /></x-ui.button>
                                </div>
                            </div>
                            <div class="mt-2.5 flex flex-wrap items-center gap-1.5 border-t border-base pt-2.5">
                                <span class="text-[11px] font-medium text-muted">{{ __('Objectifs') }}</span>
                                @forelse ($ad->objectives as $objective)
                                    <x-ui.badge :color="$objective->type->value === 'funnel' ? 'blue' : 'emerald'">
                                        <x-ui.icon :name="$objective->type->value === 'funnel' ? 'funnel' : 'bolt'" class="h-3 w-3" />
                                        {{ $objectiveLabels[$objective->type->value.':'.$objective->reference] ?? $objective->reference }}
                                    </x-ui.badge>
                                @empty
                                    <span class="text-[11px] text-muted">{{ __('aucun') }}</span>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mt-5 text-[12px] text-muted">{{ __('Aucune pub dans cette campagne. Ajoutez-en une pour la suivre.') }}</p>
            @endif
        </x-ui.card>
    @empty
        <x-ui.empty-state icon="megaphone" :title="__('Aucune campagne')" :description="__('Créez une campagne pour commencer à mesurer vos pubs.')" />
    @endforelse

    {{-- Modals: visibility driven by Livewire state (Livewire-safe, no teleport) --}}
    <div x-on:keydown.escape.window="$wire.modal !== '' && $wire.closeModal()">

        {{-- Campaign --}}
        <div x-show="$wire.modal === 'campaign'" x-cloak class="fixed inset-0 z-50">
            <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm dark:bg-black/60"></div>
            <div class="relative flex min-h-full items-center justify-center p-4" @click.self="$wire.closeModal()">
                <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-base bg-surface p-5 shadow-xl">
                    <h3 class="text-[13px] font-semibold text-primary">{{ $campaignId ? __('Modifier la campagne') : __('Nouvelle campagne') }}</h3>
                    <form wire:submit="saveCampaign" class="mt-4 space-y-4">
                        <x-ui.form-group :label="__('Nom')" for="campaignName">
                            <x-ui.input wire:model="campaignName" id="campaignName" placeholder="{{ __('Ex. Été 2026') }}" :error="$errors->has('campaignName')" />
                        </x-ui.form-group>
                        <x-ui.form-group :label="__('Plateforme')" for="campaignPlatform" :hint="__('Optionnel : Meta, Google...')">
                            <x-ui.input wire:model="campaignPlatform" id="campaignPlatform" :error="$errors->has('campaignPlatform')" />
                        </x-ui.form-group>

                        <x-ui.form-group :label="__('Conditions d\'URL')" :hint="__('La campagne correspond si TOUS ces paramètres sont présents dans l\'URL de la visite.')">
                            <div class="space-y-2">
                                @foreach ($campaignConditions as $index => $condition)
                                    <div wire:key="cc-{{ $index }}" class="flex items-center gap-2">
                                        <x-ui.input wire:model="campaignConditions.{{ $index }}.param" placeholder="{{ __('paramètre') }}" class="flex-1" :error="$errors->has('campaignConditions.'.$index.'.param')" />
                                        <span class="text-muted">=</span>
                                        <x-ui.input wire:model="campaignConditions.{{ $index }}.value" placeholder="{{ __('valeur') }}" class="flex-1" :error="$errors->has('campaignConditions.'.$index.'.value')" />
                                        <button type="button" wire:click="removeCampaignCondition({{ $index }})" @class(['shrink-0 cursor-pointer text-muted transition-colors hover:text-red-600', 'pointer-events-none opacity-30' => count($campaignConditions) <= 1]) aria-label="{{ __('Retirer') }}"><x-ui.icon name="x-mark" class="h-4 w-4" /></button>
                                    </div>
                                @endforeach
                            </div>
                            <x-ui.button type="button" variant="ghost" size="compact" wire:click="addCampaignCondition" class="mt-2"><x-ui.icon name="plus" class="h-3.5 w-3.5" /> {{ __('Ajouter une condition') }}</x-ui.button>
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
                            <x-ui.input wire:model="adName" id="adName" placeholder="{{ __('Ex. Cabriolet') }}" :error="$errors->has('adName')" />
                        </x-ui.form-group>

                        <x-ui.form-group :label="__('Conditions d\'URL')" :hint="__('La pub correspond si TOUS ces paramètres sont présents dans l\'URL.')">
                            <div class="space-y-2">
                                @foreach ($adConditions as $index => $condition)
                                    <div wire:key="ac-{{ $index }}" class="flex items-center gap-2">
                                        <x-ui.input wire:model="adConditions.{{ $index }}.param" placeholder="{{ __('paramètre') }}" class="flex-1" :error="$errors->has('adConditions.'.$index.'.param')" />
                                        <span class="text-muted">=</span>
                                        <x-ui.input wire:model="adConditions.{{ $index }}.value" placeholder="{{ __('valeur') }}" class="flex-1" :error="$errors->has('adConditions.'.$index.'.value')" />
                                        <button type="button" wire:click="removeAdCondition({{ $index }})" @class(['shrink-0 cursor-pointer text-muted transition-colors hover:text-red-600', 'pointer-events-none opacity-30' => count($adConditions) <= 1]) aria-label="{{ __('Retirer') }}"><x-ui.icon name="x-mark" class="h-4 w-4" /></button>
                                    </div>
                                @endforeach
                            </div>
                            <x-ui.button type="button" variant="ghost" size="compact" wire:click="addAdCondition" class="mt-2"><x-ui.icon name="plus" class="h-3.5 w-3.5" /> {{ __('Ajouter une condition') }}</x-ui.button>
                        </x-ui.form-group>

                        <div class="flex justify-end">
                            <x-ui.button wire:click="saveAd">{{ $adId ? __('Enregistrer') : __('Créer la pub') }}</x-ui.button>
                        </div>
                    </div>

                    @if ($editingAd)
                        <div class="mt-5 border-t border-subtle pt-4">
                            <x-ui.section-header :title="__('Objectifs de conversion')" class="mb-1" />
                            <p class="mb-3 text-[12px] text-secondary">{{ __('Cette pub n\'est créditée que des conversions ci-dessous.') }}</p>

                            @if ($editingAd->objectives->isNotEmpty())
                                <div class="mb-3 space-y-1.5">
                                    @foreach ($editingAd->objectives as $objective)
                                        <div wire:key="obj-{{ $objective->id }}" class="flex items-center justify-between gap-2 rounded-lg bg-elevated px-3 py-2">
                                            <span class="inline-flex min-w-0 items-center gap-1.5 text-[12px] text-secondary">
                                                <x-ui.icon :name="$objective->type->value === 'funnel' ? 'funnel' : 'bolt'" class="h-3.5 w-3.5 shrink-0 text-muted" />
                                                <span class="truncate">{{ $objectiveLabels[$objective->type->value.':'.$objective->reference] ?? $objective->reference }}</span>
                                                @if ($objective->type->value === 'event')<span class="shrink-0 text-muted">· {{ (float) $objective->value }} {{ __('pts') }}</span>@endif
                                            </span>
                                            <button type="button" wire:click="removeObjective({{ $objective->id }})" class="shrink-0 cursor-pointer text-muted transition-colors hover:text-red-600" aria-label="{{ __('Retirer') }}"><x-ui.icon name="x-mark" class="h-4 w-4" /></button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Searchable objective picker --}}
                            <div class="rounded-lg border border-subtle p-3">
                                <div x-data="{ open: false, search: '' }" @click.outside="open = false" class="relative">
                                    <button type="button" @click="open = !open" class="flex w-full cursor-pointer items-center justify-between rounded-lg border border-base bg-elevated px-3 py-2 text-left text-[13px] text-secondary transition-colors hover:bg-page">
                                        <span>{{ $objReference !== '' ? $selectedObjectiveLabel : __('Choisir un entonnoir ou un événement...') }}</span>
                                        <x-ui.icon name="chevron-down" class="h-4 w-4 text-muted" />
                                    </button>
                                    <div x-show="open" x-cloak x-transition.opacity class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-base bg-surface shadow-xl">
                                        <div class="border-b border-subtle p-2">
                                            <input x-model="search" @click.stop type="text" placeholder="{{ __('Rechercher...') }}" class="w-full rounded-lg border border-base bg-elevated px-2.5 py-1.5 text-[13px] text-primary placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-gray-900/10 dark:focus:ring-white/10">
                                        </div>
                                        <div class="max-h-56 overflow-y-auto p-1">
                                            <p class="px-2 pb-1 pt-1.5 text-[10px] font-semibold uppercase tracking-wide text-muted">{{ __('Entonnoirs') }}</p>
                                            @foreach ($funnelOptions as $option)
                                                <button type="button" x-show="@js(Str::lower($option['label'])).includes(search.toLowerCase())" wire:click="selectObjective('funnel', @js($option['reference']))" @click="open = false; search = ''" class="flex w-full cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-left text-[12px] text-secondary transition-colors hover:bg-elevated">
                                                    <x-ui.icon name="funnel" class="h-3.5 w-3.5 shrink-0 text-muted" /> <span class="truncate">{{ $option['label'] }}</span>
                                                </button>
                                            @endforeach
                                            <p class="px-2 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-wide text-muted">{{ __('Événements') }}</p>
                                            @foreach ($eventOptions as $option)
                                                <button type="button" x-show="@js(Str::lower($option['label'])).includes(search.toLowerCase())" wire:click="selectObjective('event', @js($option['reference']), @js($option['value']))" @click="open = false; search = ''" class="flex w-full cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-left text-[12px] text-secondary transition-colors hover:bg-elevated">
                                                    <x-ui.icon name="bolt" class="h-3.5 w-3.5 shrink-0 text-muted" /> <span class="truncate">{{ $option['label'] }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                @if ($objReference !== '')
                                    <div class="mt-3 flex items-end gap-2">
                                        @if ($objType === 'event')
                                            <x-ui.form-group :label="__('Valeur (points)')" for="objValue" class="w-28">
                                                <x-ui.input type="number" step="0.01" min="0" wire:model="objValue" id="objValue" :error="$errors->has('objValue')" />
                                            </x-ui.form-group>
                                        @endif
                                        <x-ui.button type="button" wire:click="addObjective" class="shrink-0"><x-ui.icon name="plus" class="h-3.5 w-3.5" /> {{ __('Ajouter l\'objectif') }}</x-ui.button>
                                    </div>
                                @endif
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
