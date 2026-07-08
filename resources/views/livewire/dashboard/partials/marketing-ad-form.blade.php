@php use Illuminate\Support\Str; @endphp

{{-- Ad create/edit modal (name, URL conditions, conversion objectives). Shared by
     the campaign detail and ad detail screens via the EditsAd trait. --}}
<div x-show="$wire.modal === 'ad'" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm dark:bg-black/60"></div>
    <div class="relative flex min-h-full items-center justify-center p-4" @click.self="$wire.closeModal()">
        <div class="w-full max-w-lg rounded-xl border border-base bg-surface p-5 shadow-xl">
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

                <div class="border-t border-subtle pt-4">
                    <p class="text-[13px] font-medium text-primary">{{ __('Objectifs de conversion') }}</p>
                    <p class="mb-3 mt-0.5 text-[12px] text-secondary">{{ __('Cette pub n\'est créditée que des conversions ci-dessous.') }}</p>

                    @if ($objectives !== [])
                        <div class="mb-3 space-y-1.5">
                            @foreach ($objectives as $index => $objective)
                                <div wire:key="obj-{{ $index }}" class="flex items-center gap-2 rounded-lg bg-elevated px-3 py-2">
                                    <x-ui.icon :name="$objective['type'] === 'funnel' ? 'funnel' : 'bolt'" class="h-3.5 w-3.5 shrink-0 text-muted" />
                                    <span class="min-w-0 flex-1 truncate text-[12px] text-secondary">{{ $objective['label'] }}</span>
                                    <button type="button" wire:click="removeObjective({{ $index }})" class="shrink-0 cursor-pointer text-muted transition-colors hover:text-red-600" aria-label="{{ __('Retirer') }}"><x-ui.icon name="x-mark" class="h-4 w-4" /></button>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="flex flex-wrap gap-2">
                        {{-- Tunnel picker --}}
                        <div x-data="{ open: false, search: '' }" @click.outside="open = false" class="relative">
                            <x-ui.button type="button" variant="secondary" size="compact" x-on:click="open = ! open; search = ''"><x-ui.icon name="funnel" class="h-3.5 w-3.5" /> {{ __('Tunnel') }}</x-ui.button>
                            <div x-show="open" x-cloak x-transition.opacity class="absolute bottom-full left-0 z-30 mb-1 w-72 overflow-hidden rounded-lg border border-base bg-surface shadow-xl">
                                <div class="border-b border-subtle p-2">
                                    <input x-model="search" x-on:click.stop type="text" placeholder="{{ __('Rechercher un tunnel...') }}" class="w-full rounded-lg border border-base bg-elevated px-2.5 py-1.5 text-[13px] text-primary placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-gray-900/10 dark:focus:ring-white/10">
                                </div>
                                <div class="max-h-52 overflow-y-auto p-1">
                                    @forelse ($funnelOptions as $option)
                                        <button type="button" x-show="@js(Str::lower($option['label'])).includes(search.toLowerCase())" wire:click="addObjective('funnel', @js($option['reference']), @js($option['label']))" x-on:click="open = false" class="flex w-full cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-left text-[12px] text-secondary transition-colors hover:bg-elevated">
                                            <x-ui.icon name="funnel" class="h-3.5 w-3.5 shrink-0 text-muted" /> <span class="truncate">{{ $option['label'] }}</span>
                                        </button>
                                    @empty
                                        <p class="px-2 py-2 text-[12px] text-muted">{{ __('Aucun tunnel disponible.') }}</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        {{-- Event picker --}}
                        <div x-data="{ open: false, search: '' }" @click.outside="open = false" class="relative">
                            <x-ui.button type="button" variant="secondary" size="compact" x-on:click="open = ! open; search = ''"><x-ui.icon name="bolt" class="h-3.5 w-3.5" /> {{ __('Événement') }}</x-ui.button>
                            <div x-show="open" x-cloak x-transition.opacity class="absolute bottom-full left-0 z-30 mb-1 w-72 overflow-hidden rounded-lg border border-base bg-surface shadow-xl">
                                <div class="border-b border-subtle p-2">
                                    <input x-model="search" x-on:click.stop type="text" placeholder="{{ __('Rechercher un événement...') }}" class="w-full rounded-lg border border-base bg-elevated px-2.5 py-1.5 text-[13px] text-primary placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-gray-900/10 dark:focus:ring-white/10">
                                </div>
                                <div class="max-h-52 overflow-y-auto p-1">
                                    @forelse ($eventOptions as $option)
                                        <button type="button" x-show="@js(Str::lower($option['label'])).includes(search.toLowerCase())" wire:click="addObjective('event', @js($option['reference']), @js($option['label']))" x-on:click="open = false" class="flex w-full cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-left text-[12px] text-secondary transition-colors hover:bg-elevated">
                                            <x-ui.icon name="bolt" class="h-3.5 w-3.5 shrink-0 text-muted" /> <span class="truncate">{{ $option['label'] }}</span>
                                        </button>
                                    @empty
                                        <p class="px-2 py-2 text-[12px] text-muted">{{ __('Aucun événement disponible.') }}</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button type="button" variant="ghost" wire:click="closeModal">{{ __('Annuler') }}</x-ui.button>
                    <x-ui.button type="button" wire:click="saveAd">{{ __('Enregistrer') }}</x-ui.button>
                </div>
            </div>
        </div>
    </div>
</div>
