@php
    use Illuminate\Support\Str;
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
                <h1 class="an:text-2xl an:font-semibold an:tracking-tight an:text-primary">{{ $campaign->name }}</h1>
                @if ($campaign->platform)<x-ui::badge color="gray">{{ $campaign->platform }}</x-ui::badge>@endif
            </div>
            <div class="an:mt-1.5 an:flex an:flex-wrap an:items-center an:gap-1.5">
                @forelse ($campaign->match_conditions ?? [] as $condition)
                    <x-analytics::condition-chip :param="$condition['param']" :value="$condition['value']" />
                @empty
                    <span class="an:inline-flex an:items-center an:gap-1 an:text-[11px] an:text-amber-600 an:dark:text-amber-400"><x-ui::icon name="exclamation-triangle" class="an:h-3.5 an:w-3.5" /> {{ __('Aucune condition : ne correspondra à aucun trafic') }}</span>
                @endforelse
            </div>
        </div>
        <div class="an:flex an:shrink-0 an:items-center an:gap-2">
            <x-ui::button variant="secondary" x-on:click="$anOpenWhenDone($wire.editCampaign(), 'an-campaign-form')"><x-ui::icon name="pencil-square" class="an:h-4 an:w-4" /> {{ __('Modifier') }}</x-ui::button>
            <x-ui::button variant="ghost" x-on:click="$dispatch('ui-open-modal', 'an-campaign-delete')" aria-label="{{ __('Supprimer') }}"><x-ui::icon name="trash" class="an:h-4 an:w-4" /></x-ui::button>
        </div>
    </div>

    {{-- Performance (deferred content : KPIs, trend and conversions) --}}
    <div>
        <div class="an:mb-4 an:flex an:flex-wrap an:items-center an:justify-between an:gap-3">
            <x-ui::section-header :title="__('Performance')" :description="__('du :from au :to', ['from' => $range->from->isoFormat('D MMM'), 'to' => $range->to->isoFormat('D MMM YYYY')])" />
            @include('analytics::livewire.dashboard.partials.filters')
        </div>
        <livewire:analytics::admin.widgets.campaign-detail-content :period="$period" :subject="$subject" :ref-id="$campaign->id" :key="'campaign-content-'.$campaign->id.'-'.$period.'-'.$subject" />
    </div>

    {{-- Ads --}}
    <div>
        <div class="an:mb-4 an:flex an:items-center an:justify-between">
            <x-ui::section-header :title="__('Pubs')" :description="__('Les objectifs de conversion se définissent par pub.')" />
            <x-ui::button variant="secondary" size="compact" x-on:click="$anOpenWhenDone($wire.newAd(), 'an-ad-form')"><x-ui::icon name="plus" class="an:h-3.5 an:w-3.5" /> {{ __('Nouvelle pub') }}</x-ui::button>
        </div>

        @if ($ads->isEmpty())
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
                        @php $adUrl = route('analytics.admin.marketing.ads.show', $ad->id); @endphp
                        <x-ui::table.row
                            wire:key="ad-{{ $ad->id }}"
                            class="an-row-link">
                            <x-ui::table.cell :first="true" variant="primary">
                                <a href="{{ $adUrl }}" class="an-row-link__target an:cursor-pointer an:text-[13px] an:font-medium an:text-primary an:hover:underline">{{ $ad->name }}</a>
                            </x-ui::table.cell>
                            <x-ui::table.cell>
                                <div class="an:flex an:flex-wrap an:items-center an:gap-1.5">
                                    @foreach ($ad->match_conditions ?? [] as $condition)
                                        <x-analytics::condition-chip :param="$condition['param']" :value="$condition['value']" />
                                    @endforeach
                                </div>
                            </x-ui::table.cell>
                            <x-ui::table.cell>
                                <div class="an:flex an:flex-wrap an:items-center an:gap-1.5">
                                    @forelse ($ad->objectives as $objective)
                                        <x-ui::badge :color="$objective->type->value === 'funnel' ? 'blue' : 'emerald'">
                                            <x-ui::icon :name="$objective->type->value === 'funnel' ? 'funnel' : 'bolt'" class="an:h-3 an:w-3" />
                                            {{ $objectiveLabels[$objective->type->value.':'.$objective->reference] ?? $objective->reference }}
                                        </x-ui::badge>
                                    @empty
                                        <span class="an:text-[11px] an:text-muted">{{ __('aucun') }}</span>
                                    @endforelse
                                </div>
                            </x-ui::table.cell>
                            <x-ui::table.cell align="right" class="an:tabular-nums">@if ($adMetrics === [])<span class="an:inline-block an:h-3 an:w-8 an:animate-pulse an:rounded an:bg-elevated an:align-middle"></span>@else{{ number_format($adMetrics[$ad->id]['sessions'] ?? 0, 0, ',', ' ') }}@endif</x-ui::table.cell>
                            <x-ui::table.cell align="right" class="an:font-medium an:tabular-nums an:text-primary">@if ($adMetrics === [])<span class="an:inline-block an:h-3 an:w-6 an:animate-pulse an:rounded an:bg-elevated an:align-middle"></span>@else{{ number_format($adConversions[$ad->id] ?? 0, 0, ',', ' ') }}@endif</x-ui::table.cell>
                            <x-ui::table.cell :last="true" align="right">
                                <div class="an-row-link__above an:flex an:items-center an:justify-end an:gap-1">
                                    <x-ui::button variant="ghost" size="compact" x-on:click="$anOpenWhenDone($wire.editAd({{ $ad->id }}), 'an-ad-form')" aria-label="{{ __('Modifier') }}"><x-ui::icon name="pencil-square" class="an:h-3.5 an:w-3.5" /></x-ui::button>
                                    <x-ui::button variant="ghost" size="compact" x-on:click="$anOpenWhenDone($wire.confirmDeleteAd({{ $ad->id }}), 'an-ad-delete')" aria-label="{{ __('Supprimer') }}"><x-ui::icon name="trash" class="an:h-3.5 an:w-3.5" /></x-ui::button>
                                </div>
                            </x-ui::table.cell>
                        </x-ui::table.row>
                    @endforeach
                </x-ui::table.body>
            </x-ui::table>
        @endif
    </div>

    {{-- Campaign edit --}}
    <x-ui::modal name="an-campaign-form" :title="__('Modifier la campagne')">
        <form id="an-campaign-form-fields" x-on:submit.prevent="$anCloseWhenDone($wire.saveCampaign(), 'an-campaign-form')" class="an:space-y-4">
            <x-ui::form-group :label="__('Nom')" for="campaignName">
                <x-ui::input wire:model="campaignName" id="campaignName" :error="$errors->has('campaignName')" />
            </x-ui::form-group>
            <x-ui::form-group :label="__('Plateforme')" for="campaignPlatform" :hint="__('Optionnel : Meta, Google...')">
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

    {{-- Ad create/edit --}}
    @include('analytics::livewire.dashboard.partials.marketing-ad-form')

    {{-- Delete campaign --}}
    <x-ui::modal name="an-campaign-delete" variant="confirm" :title="__('Supprimer la campagne ?')">
        {{ __('« :name » et toutes ses pubs et objectifs seront supprimés. Le trafic déjà capté reste en base.', ['name' => $campaign->name]) }}

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
