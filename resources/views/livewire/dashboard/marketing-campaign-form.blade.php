<x-analytics::root area="admin">
    <x-ui::modal name="an-campaign-form" :title="$campaignId ? __('Modifier la campagne') : __('Nouvelle campagne')">
        <form id="an-campaign-form-fields" x-on:submit.prevent="$anCloseWhenDone($wire.saveCampaign(), 'an-campaign-form')" class="an:space-y-4">
            <x-ui::form-group :label="__('Nom')" for="campaignName">
                <x-ui::input wire:model="campaignName" id="campaignName" placeholder="{{ __('Ex. Été 2026') }}" :error="$errors->has('campaignName')" />
            </x-ui::form-group>
            <x-ui::form-group :label="__('Plateforme')" for="campaignPlatform" :hint='__("Optionnel\u{00A0}: Meta, Google...")'>
                <x-ui::input wire:model="campaignPlatform" id="campaignPlatform" :error="$errors->has('campaignPlatform')" />
            </x-ui::form-group>
            <x-ui::form-group :label="__('Conditions d\'URL')" :hint="__('La campagne correspond si TOUS ces paramètres sont présents dans l\'URL.')" :error="$errors->first('campaignConditions.*') ?: $errors->first('campaignConditions')">
                <div class="an:space-y-2">
                    @foreach ($campaignConditions as $index => $condition)
                        <div wire:key="cc-{{ $index }}" class="an:flex an:items-center an:gap-2">
                            <x-ui::input wire:model="campaignConditions.{{ $index }}.param" placeholder="{{ __('paramètre') }}" class="an:flex-1" :error="$errors->has('campaignConditions.'.$index.'.param')" />
                            <span class="an:text-muted">=</span>
                            <x-ui::input wire:model="campaignConditions.{{ $index }}.value" placeholder="{{ __('valeur') }}" class="an:flex-1" :error="$errors->has('campaignConditions.'.$index.'.value')" />
                            <button type="button" wire:click="removeCampaignCondition({{ $index }})" class="an:shrink-0 an:cursor-pointer an:text-muted an:transition-colors an:hover:text-red-600 an:disabled:pointer-events-none an:disabled:opacity-30" @disabled(count($campaignConditions) <= 1) aria-label="{{ __('Retirer') }}"><x-ui::icon name="x-mark" class="an:h-4 an:w-4" /></button>
                        </div>
                    @endforeach
                </div>
                <x-ui::button type="button" variant="ghost" size="compact" wire:click="addCampaignCondition" class="an:mt-2"><x-ui::icon name="plus" class="an:h-3.5 an:w-3.5" /> {{ __('Ajouter une condition') }}</x-ui::button>
            </x-ui::form-group>
        </form>

        <x-slot:footer>
            <x-ui::button type="button" variant="ghost" x-on:click="$dispatch('ui-close-modal', 'an-campaign-form')">{{ __('Annuler') }}</x-ui::button>
            <x-ui::button type="submit" form="an-campaign-form-fields" :loading="true" target="saveCampaign">{{ __('Enregistrer') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::modal>
</x-analytics::root>
