@php use Illuminate\Support\Str; @endphp

{{-- Ad create/edit modal, shared by the campaign detail and ad detail screens. --}}
<x-ui::modal name="an-ad-form" :title="$adId ? __('Modifier la pub') : __('Nouvelle pub')">
    <form id="an-ad-form-fields" x-on:submit.prevent="$anCloseWhenDone($wire.saveAd(), 'an-ad-form')" class="an:space-y-4">
        <x-ui::form-group :label="__('Nom')" for="adName">
            <x-ui::input wire:model="adName" id="adName" placeholder="{{ __('Ex. Cabriolet') }}" :error="$errors->has('adName')" />
        </x-ui::form-group>

        <x-ui::form-group :label="__('Conditions d\'URL')" :hint="__('La pub correspond si TOUS ces paramètres sont présents dans l\'URL.')" :error="$errors->first('adConditions.*') ?: $errors->first('adConditions')">
            <div class="an:space-y-2">
                @foreach ($adConditions as $index => $condition)
                    <div wire:key="ac-{{ $index }}" class="an:flex an:items-center an:gap-2">
                        <x-ui::input wire:model="adConditions.{{ $index }}.param" placeholder="{{ __('paramètre') }}" class="an:flex-1" :error="$errors->has('adConditions.'.$index.'.param')" />
                        <span class="an:text-muted">=</span>
                        <x-ui::input wire:model="adConditions.{{ $index }}.value" placeholder="{{ __('valeur') }}" class="an:flex-1" :error="$errors->has('adConditions.'.$index.'.value')" />
                        <button type="button" wire:click="removeAdCondition({{ $index }})" class="an:shrink-0 an:cursor-pointer an:text-muted an:transition-colors an:hover:text-red-600 an:disabled:pointer-events-none an:disabled:opacity-30" @disabled(count($adConditions) <= 1) aria-label="{{ __('Retirer') }}"><x-ui::icon name="x-mark" class="an:h-4 an:w-4" /></button>
                    </div>
                @endforeach
            </div>
            <x-ui::button type="button" variant="ghost" size="compact" wire:click="addAdCondition" class="an:mt-2"><x-ui::icon name="plus" class="an:h-3.5 an:w-3.5" /> {{ __('Ajouter une condition') }}</x-ui::button>
        </x-ui::form-group>

        <div class="an:border-t an:border-subtle an:pt-4">
            <p class="an:text-[13px] an:font-medium an:text-primary">{{ __('Objectifs de conversion') }}</p>
            <p class="an:mb-3 an:mt-0.5 an:text-[12px] an:text-secondary">{{ __('Cette pub n\'est créditée que des conversions ci-dessous.') }}</p>

            @if ($errors->first('objectives.*'))
                <p class="an:mb-2 an:text-[12px] an:text-red-500 an:dark:text-red-400">{{ $errors->first('objectives.*') }}</p>
            @endif

            @if ($objectives !== [])
                <div class="an:mb-3 an:space-y-1.5">
                    @foreach ($objectives as $index => $objective)
                        <div wire:key="obj-{{ $index }}" class="an:flex an:items-center an:gap-2 an:rounded-lg an:bg-elevated an:px-3 an:py-2">
                            <x-ui::icon :name="$objective['type'] === 'funnel' ? 'funnel' : 'bolt'" class="an:h-3.5 an:w-3.5 an:shrink-0 an:text-muted" />
                            <span class="an:min-w-0 an:flex-1 an:truncate an:text-[12px] an:text-secondary">{{ $objective['label'] }}</span>
                            <button type="button" wire:click="removeObjective({{ $index }})" class="an:shrink-0 an:cursor-pointer an:text-muted an:transition-colors an:hover:text-red-600" aria-label="{{ __('Retirer') }}"><x-ui::icon name="x-mark" class="an:h-4 an:w-4" /></button>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="an:flex an:flex-wrap an:gap-2">
                {{-- Tunnel picker --}}
                <div x-data="anObjectivePicker" @click.outside="open = false" x-on:keydown.escape="closeOnEscape($event)" class="an:relative">
                    <x-ui::button type="button" variant="secondary" size="compact" x-ref="trigger" x-bind:aria-expanded="open" x-on:click="toggle()"><x-ui::icon name="funnel" class="an:h-3.5 an:w-3.5" /> {{ __('Tunnel') }}</x-ui::button>
                    <div x-show="open" x-cloak x-transition.opacity class="an:absolute an:bottom-full an:left-0 an:z-30 an:mb-1 an:w-72 an:overflow-hidden an:rounded-lg an:border an:border-default an:bg-surface an:shadow-xl">
                        <div class="an:border-b an:border-subtle an:p-2">
                            <input x-model="search" x-on:click.stop x-on:keydown.enter.prevent type="text" placeholder="{{ __('Rechercher un tunnel...') }}" class="an:w-full an:rounded-lg an:border an:border-default an:bg-elevated an:px-2.5 an:py-1.5 an:text-[13px] an:text-primary an:placeholder:text-muted an:focus:outline-none an:focus:ring-2 an:focus:ring-gray-900/10 an:dark:focus:ring-white/10">
                        </div>
                        <div class="an:max-h-52 an:overflow-y-auto an:p-1">
                            @forelse ($funnelOptions as $option)
                                <button type="button" x-show="@js(Str::lower($option['label'])).includes(search.toLowerCase())" wire:click="addObjective('funnel', @js($option['reference']), @js($option['label']))" x-on:click="open = false" class="an:flex an:w-full an:cursor-pointer an:items-center an:gap-2 an:rounded-lg an:px-2 an:py-1.5 an:text-left an:text-[12px] an:text-secondary an:transition-colors an:hover:bg-elevated">
                                    <x-ui::icon name="funnel" class="an:h-3.5 an:w-3.5 an:shrink-0 an:text-muted" /> <span class="an:truncate">{{ $option['label'] }}</span>
                                </button>
                            @empty
                                <p class="an:px-2 an:py-2 an:text-[12px] an:text-muted">{{ __('Aucun tunnel disponible.') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Event picker --}}
                <div x-data="anObjectivePicker" @click.outside="open = false" x-on:keydown.escape="closeOnEscape($event)" class="an:relative">
                    <x-ui::button type="button" variant="secondary" size="compact" x-ref="trigger" x-bind:aria-expanded="open" x-on:click="toggle()"><x-ui::icon name="bolt" class="an:h-3.5 an:w-3.5" /> {{ __('Événement') }}</x-ui::button>
                    <div x-show="open" x-cloak x-transition.opacity class="an:absolute an:bottom-full an:left-0 an:z-30 an:mb-1 an:w-72 an:overflow-hidden an:rounded-lg an:border an:border-default an:bg-surface an:shadow-xl">
                        <div class="an:border-b an:border-subtle an:p-2">
                            <input x-model="search" x-on:click.stop x-on:keydown.enter.prevent type="text" placeholder="{{ __('Rechercher un événement...') }}" class="an:w-full an:rounded-lg an:border an:border-default an:bg-elevated an:px-2.5 an:py-1.5 an:text-[13px] an:text-primary an:placeholder:text-muted an:focus:outline-none an:focus:ring-2 an:focus:ring-gray-900/10 an:dark:focus:ring-white/10">
                        </div>
                        <div class="an:max-h-52 an:overflow-y-auto an:p-1">
                            @forelse ($eventOptions as $option)
                                <button type="button" x-show="@js(Str::lower($option['label'])).includes(search.toLowerCase())" wire:click="addObjective('event', @js($option['reference']), @js($option['label']))" x-on:click="open = false" class="an:flex an:w-full an:cursor-pointer an:items-center an:gap-2 an:rounded-lg an:px-2 an:py-1.5 an:text-left an:text-[12px] an:text-secondary an:transition-colors an:hover:bg-elevated">
                                    <x-ui::icon name="bolt" class="an:h-3.5 an:w-3.5 an:shrink-0 an:text-muted" /> <span class="an:truncate">{{ $option['label'] }}</span>
                                </button>
                            @empty
                                <p class="an:px-2 an:py-2 an:text-[12px] an:text-muted">{{ __('Aucun événement disponible.') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <x-slot:footer>
        <x-ui::button type="button" variant="ghost" x-on:click="$dispatch('ui-close-modal', 'an-ad-form')">{{ __('Annuler') }}</x-ui::button>
        <x-ui::button type="submit" form="an-ad-form-fields" :loading="true" target="saveAd">{{ __('Enregistrer') }}</x-ui::button>
    </x-slot:footer>
</x-ui::modal>
