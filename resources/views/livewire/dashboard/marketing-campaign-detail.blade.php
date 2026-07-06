@php
    use Illuminate\Support\Str;

    $routeName = config('analytics.marketing.route_name', 'marketing');
@endphp

<div class="space-y-6">

    <div>
        <a href="{{ route($routeName.'.campaigns') }}" class="inline-flex cursor-pointer items-center gap-x-1 text-[12px] font-medium text-secondary transition-colors hover:text-primary">
            <x-ui.icon name="arrow-left" class="h-3.5 w-3.5" />
            {{ __('Retour aux campagnes') }}
        </a>
    </div>

    {{-- Campaign header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-semibold tracking-tight text-primary">{{ $campaign->name }}</h1>
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
        <div class="flex shrink-0 items-center gap-2">
            <x-ui.button variant="secondary" wire:click="editCampaign"><x-ui.icon name="pencil-square" class="h-4 w-4" /> {{ __('Modifier') }}</x-ui.button>
            <x-ui.button variant="ghost" wire:click="confirmDeleteCampaign" aria-label="{{ __('Supprimer') }}"><x-ui.icon name="trash" class="h-4 w-4" /></x-ui.button>
        </div>
    </div>

    {{-- Performance --}}
    <div>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <x-ui.section-header :title="__('Performance')" :description="__('du :from au :to', ['from' => $range->from->isoFormat('D MMM'), 'to' => $range->to->isoFormat('D MMM YYYY')])" />
            @include('analytics::livewire.dashboard.partials.filters')
        </div>
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-analytics::kpi-card :label="__('Sessions')" :value="number_format($sessions, 0, ',', ' ')" icon="cursor-arrow-rays" :metric="$sessionsDelta">
                <div wire:key="c-spark-s-{{ $range->days }}-{{ $subject }}" class="mt-3"><x-analytics::sparkline :values="$trendData" /></div>
            </x-analytics::kpi-card>
            <x-analytics::kpi-card :label="__('Visiteurs')" :value="number_format($visitors, 0, ',', ' ')" icon="users" :metric="$visitorsDelta">
                <div wire:key="c-spark-v-{{ $range->days }}-{{ $subject }}" class="mt-3"><x-analytics::sparkline :values="$trendData" /></div>
            </x-analytics::kpi-card>
            <x-analytics::kpi-card :label="__('Conversions')" :value="number_format($conversions, 0, ',', ' ')" icon="check-circle" :metric="$conversionsDelta" />
            <x-analytics::kpi-card :label="__('Taux de conversion')" :value="$rateLabel" icon="arrow-trending-up" :metric="$rateDelta" />
        </div>
        <x-ui.card class="mt-6">
            <x-ui.section-header :title="__('Sessions au fil du temps')" class="mb-4" />
            @if (array_sum($trendData) > 0)
                <x-analytics::area-chart wire:key="mkt-ctrend-{{ $range->days }}-{{ $subject }}" :labels="$trendLabels" :data="$trendData" :label="__('Sessions')" height="h-56" />
            @else
                <div class="flex h-56 items-center justify-center rounded-lg bg-elevated text-[12px] text-muted">{{ __('Aucune session sur la période.') }}</div>
            @endif
        </x-ui.card>
    </div>

    {{-- Ads --}}
    <div>
        <div class="mb-4 flex items-center justify-between">
            <x-ui.section-header :title="__('Pubs')" :description="__('Les objectifs de conversion se définissent par pub.')" />
            <x-ui.button variant="secondary" size="compact" wire:click="newAd"><x-ui.icon name="plus" class="h-3.5 w-3.5" /> {{ __('Nouvelle pub') }}</x-ui.button>
        </div>

        @if ($ads->isEmpty())
            <x-ui.empty-state icon="rectangle-stack" :title="__('Aucune pub')" :description="__('Ajoutez une pub à cette campagne pour la suivre.')" />
        @else
            <x-ui.table>
                <x-ui.table.head>
                    <x-ui.table.header-cell :first="true">{{ __('Pub') }}</x-ui.table.header-cell>
                    <x-ui.table.header-cell>{{ __('Conditions') }}</x-ui.table.header-cell>
                    <x-ui.table.header-cell>{{ __('Objectifs') }}</x-ui.table.header-cell>
                    <x-ui.table.header-cell align="right">{{ __('Sessions') }}</x-ui.table.header-cell>
                    <x-ui.table.header-cell align="right">{{ __('Conv.') }}</x-ui.table.header-cell>
                    <x-ui.table.header-cell :last="true" align="right">{{ __('Actions') }}</x-ui.table.header-cell>
                </x-ui.table.head>
                <x-ui.table.body>
                    @foreach ($ads as $ad)
                        @php $adUrl = route($routeName.'.ads.show', $ad->id); @endphp
                        <x-ui.table.row
                            wire:key="ad-{{ $ad->id }}"
                            onclick="if (!event.target.closest('a,button')) window.location='{{ $adUrl }}'"
                            class="cursor-pointer">
                            <x-ui.table.cell :first="true" variant="primary">
                                <a href="{{ $adUrl }}" class="cursor-pointer text-[13px] font-medium text-primary hover:underline">{{ $ad->name }}</a>
                            </x-ui.table.cell>
                            <x-ui.table.cell>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @foreach ($ad->match_conditions ?? [] as $condition)
                                        <x-analytics::condition-chip :param="$condition['param']" :value="$condition['value']" />
                                    @endforeach
                                </div>
                            </x-ui.table.cell>
                            <x-ui.table.cell>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @forelse ($ad->objectives as $objective)
                                        <x-ui.badge :color="$objective->type->value === 'funnel' ? 'blue' : 'emerald'">
                                            <x-ui.icon :name="$objective->type->value === 'funnel' ? 'funnel' : 'bolt'" class="h-3 w-3" />
                                            {{ $objectiveLabels[$objective->type->value.':'.$objective->reference] ?? $objective->reference }}
                                        </x-ui.badge>
                                    @empty
                                        <span class="text-[11px] text-muted">{{ __('aucun') }}</span>
                                    @endforelse
                                </div>
                            </x-ui.table.cell>
                            <x-ui.table.cell align="right" class="tabular-nums">{{ number_format($adMetrics[$ad->id]['sessions'] ?? 0, 0, ',', ' ') }}</x-ui.table.cell>
                            <x-ui.table.cell align="right" class="font-medium tabular-nums text-primary">{{ number_format($adConversions[$ad->id] ?? 0, 0, ',', ' ') }}</x-ui.table.cell>
                            <x-ui.table.cell :last="true" align="right">
                                <div class="flex items-center justify-end gap-1">
                                    <x-ui.button variant="ghost" size="compact" wire:click="editAd({{ $ad->id }})" aria-label="{{ __('Modifier') }}"><x-ui.icon name="pencil-square" class="h-3.5 w-3.5" /></x-ui.button>
                                    <x-ui.button variant="ghost" size="compact" wire:click="confirmDeleteAd({{ $ad->id }})" aria-label="{{ __('Supprimer') }}"><x-ui.icon name="trash" class="h-3.5 w-3.5" /></x-ui.button>
                                </div>
                            </x-ui.table.cell>
                        </x-ui.table.row>
                    @endforeach
                </x-ui.table.body>
            </x-ui.table>
        @endif
    </div>

    {{-- Modals --}}
    <div x-on:keydown.escape.window="$wire.modal !== '' && $wire.closeModal()">

        {{-- Campaign edit --}}
        <div x-show="$wire.modal === 'campaign'" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm dark:bg-black/60"></div>
            <div class="relative flex min-h-full items-center justify-center p-4" @click.self="$wire.closeModal()">
                <div class="w-full max-w-lg rounded-xl border border-base bg-surface p-5 shadow-xl">
                    <h3 class="text-[13px] font-semibold text-primary">{{ __('Modifier la campagne') }}</h3>
                    <form wire:submit="saveCampaign" class="mt-4 space-y-4">
                        <x-ui.form-group :label="__('Nom')" for="campaignName">
                            <x-ui.input wire:model="campaignName" id="campaignName" :error="$errors->has('campaignName')" />
                        </x-ui.form-group>
                        <x-ui.form-group :label="__('Plateforme')" for="campaignPlatform" :hint="__('Optionnel : Meta, Google...')">
                            <x-ui.input wire:model="campaignPlatform" id="campaignPlatform" :error="$errors->has('campaignPlatform')" />
                        </x-ui.form-group>
                        <x-ui.form-group :label="__('Conditions d\'URL')" :hint="__('La campagne correspond si TOUS ces paramètres sont présents dans l\'URL.')">
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
        <div x-show="$wire.modal === 'ad'" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm dark:bg-black/60"></div>
            <div class="relative flex min-h-full items-center justify-center p-4" @click.self="$wire.closeModal()">
                <div class="w-full max-w-lg rounded-xl border border-base bg-surface p-5 shadow-xl">
                    <h3 class="text-[13px] font-semibold text-primary">{{ $adId ? __('Modifier la pub') : __('Nouvelle pub') }}</h3>

                    <form wire:submit="saveAd" class="mt-4 space-y-4">
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
                                            @if ($objective['type'] === 'event')
                                                <div class="flex shrink-0 items-center gap-1">
                                                    <input type="number" step="0.01" min="0" wire:model.blur="objectives.{{ $index }}.value" class="w-16 rounded-lg border border-base bg-surface px-2 py-1 text-[12px] text-primary focus:outline-none focus:ring-2 focus:ring-gray-900/10 dark:focus:ring-white/10" />
                                                    <span class="text-[11px] text-muted">{{ __('pts') }}</span>
                                                </div>
                                            @endif
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
                                                <button type="button" x-show="@js(Str::lower($option['label'])).includes(search.toLowerCase())" wire:click="addObjective('event', @js($option['reference']), @js($option['label']), @js($option['value']))" x-on:click="open = false" class="flex w-full cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-left text-[12px] text-secondary transition-colors hover:bg-elevated">
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
                            <x-ui.button type="submit">{{ __('Enregistrer') }}</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Delete campaign --}}
        <div x-show="$wire.modal === 'delete-campaign'" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm dark:bg-black/60"></div>
            <div class="relative flex min-h-full items-center justify-center p-4" @click.self="$wire.closeModal()">
                <div class="w-full max-w-sm rounded-xl border border-base bg-surface p-5 shadow-xl">
                    <h3 class="text-[13px] font-semibold text-primary">{{ __('Supprimer la campagne ?') }}</h3>
                    <p class="mt-1.5 text-[12px] text-secondary">{{ __('« :name » et toutes ses pubs et objectifs seront supprimés. Le trafic déjà capté reste en base.', ['name' => $campaign->name]) }}</p>
                    <div class="mt-5 flex justify-end gap-2">
                        <x-ui.button type="button" variant="ghost" wire:click="closeModal">{{ __('Annuler') }}</x-ui.button>
                        <x-ui.button type="button" variant="danger" wire:click="deleteCampaignConfirmed">{{ __('Supprimer') }}</x-ui.button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Delete ad --}}
        <div x-show="$wire.modal === 'delete-ad'" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm dark:bg-black/60"></div>
            <div class="relative flex min-h-full items-center justify-center p-4" @click.self="$wire.closeModal()">
                <div class="w-full max-w-sm rounded-xl border border-base bg-surface p-5 shadow-xl">
                    <h3 class="text-[13px] font-semibold text-primary">{{ __('Supprimer la pub ?') }}</h3>
                    <p class="mt-1.5 text-[12px] text-secondary">{{ __('« :name » et ses objectifs seront supprimés. Le trafic déjà capté reste en base.', ['name' => $deleteAdLabel]) }}</p>
                    <div class="mt-5 flex justify-end gap-2">
                        <x-ui.button type="button" variant="ghost" wire:click="closeModal">{{ __('Annuler') }}</x-ui.button>
                        <x-ui.button type="button" variant="danger" wire:click="deleteAdConfirmed">{{ __('Supprimer') }}</x-ui.button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
