@php
    use Falcon\Analytics\Support\NumberLabel;
@endphp

<x-analytics::root area="admin" class="an:space-y-6">

    <div>
        <a href="{{ route('analytics.admin.visitors') }}" class="an:inline-flex an:cursor-pointer an:items-center an:gap-x-1 an:text-[12px] an:font-medium an:text-secondary an:transition-colors an:hover:text-primary">
            <x-ui::icon name="arrow-left" class="an:h-3.5 an:w-3.5" />
            {{ __('Retour aux visiteurs') }}
        </a>
    </div>

    {{-- Header --}}
    <div class="an:min-w-0">
        <h1 class="an:text-2xl an:font-semibold an:tracking-tight an:text-primary">{{ $detail->name }}</h1>
        <div class="an:mt-1.5 an:flex an:flex-wrap an:items-center an:gap-x-2.5 an:gap-y-1 an:text-sm an:text-secondary">
            <span>{{ $detail->kind }}</span>
            @if ($detail->isReturning)
                <x-ui::badge color="blue">{{ __('Récurrent') }}</x-ui::badge>
            @endif
            <span class="an:text-muted">·</span>
            <x-analytics::visitor-id :uuid="$detail->uuid" />
            <span class="an:text-muted">·</span>
            <span>{{ __('Première visite le :date', ['date' => $detail->firstSeenAt->translatedFormat('d M Y')]) }}</span>
        </div>
    </div>

    {{-- Engagement figures --}}
    <div class="an:grid an:grid-cols-2 an:gap-3 an:sm:grid-cols-4 an:sm:gap-4">
        <x-ui::stat-card :label="__('Sessions')" :value="(string) $detail->sessionCount" icon="rectangle-stack" />
        <x-ui::stat-card :label="__('Pages vues')" :value="(string) $detail->pageviewCount" icon="document-text" />
        <x-ui::stat-card :label="__('Durée moy.')" :value="$detail->averageDuration" icon="clock" />
        <x-ui::stat-card :label="__('Pages / session')" :value="$detail->pagesPerSession" icon="chart-bar" />
    </div>

    {{-- Behaviour: devices and acquisition --}}
    <div class="an:grid an:grid-cols-1 an:gap-6 an:lg:grid-cols-2">
        <x-ui::card>
            <x-ui::section-header :title="__('Appareils')" class="an:mb-4" />
            @if ($detail->devices !== [])
                <div class="an:flex an:items-center an:gap-5">
                    <x-analytics::donut
                        :labels="array_column($detail->devices, 'label')"
                        :values="array_column($detail->devices, 'sessions')"
                        :colors="array_column($detail->devices, 'color')"
                        :total="(string) $detail->deviceSessions"
                        :caption="__('sessions')" />
                    <div class="an:flex-1 an:space-y-2.5">
                        @foreach ($detail->devices as $share)
                            <div class="an:flex an:items-center an:justify-between an:gap-2">
                                <span class="an:flex an:items-center an:gap-2 an:text-[13px] an:text-secondary"><span class="an:h-2 an:w-2 an:rounded-full" style="background:var({{ $share->color }})"></span>{{ $share->label }}</span>
                                <span class="an:text-[13px]"><span class="an:font-semibold an:text-primary">{{ NumberLabel::percent($share->percent) }}</span> <span class="an:text-muted">{{ $share->sessions }}</span></span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <x-ui::empty-state icon="device-phone-mobile" :title="__('Aucune donnée')" />
            @endif
        </x-ui::card>

        <x-ui::card>
            <x-ui::section-header :title="__('Acquisition')" class="an:mb-4" />
            @if ($detail->sources !== [])
                <div class="an:space-y-3">
                    @foreach ($detail->sources as $share)
                        <div>
                            <div class="an:mb-1 an:flex an:items-center an:justify-between an:text-[13px]">
                                <span class="an:text-secondary"><x-analytics::source :value="$share->source" /></span>
                                <span><span class="an:font-semibold an:text-primary">{{ NumberLabel::percent($share->percent) }}</span> <span class="an:text-muted">{{ $share->sessions }}</span></span>
                            </div>
                            <div class="an:h-1 an:w-full an:overflow-hidden an:rounded-full an:bg-elevated">
                                <div class="an:h-full an:rounded-full an:bg-series-1/70" style="width: {{ $share->percent }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <x-ui::empty-state icon="globe-alt" :title="__('Aucune donnée')" />
            @endif
        </x-ui::card>
    </div>

    {{-- Sessions --}}
    <div>
        <x-ui::section-header :title="__('Sessions')" class="an:mb-4" />

        @if ($sessions->isEmpty())
            <div class="an:rounded-xl an:border an:border-default an:bg-surface an:px-5 an:py-10 an:text-center an:text-[13px] an:text-secondary">
                {{ __('Aucune session pour ce visiteur.') }}
            </div>
        @else
            <x-ui::table>
                <x-ui::table.head>
                    <x-ui::table.header-cell :first="true">{{ __('Session') }}</x-ui::table.header-cell>
                    <x-ui::table.header-cell>{{ __('Durée') }}</x-ui::table.header-cell>
                    <x-ui::table.header-cell>{{ __('Pages') }}</x-ui::table.header-cell>
                    <x-ui::table.header-cell>{{ __('Appareil') }}</x-ui::table.header-cell>
                    <x-ui::table.header-cell>{{ __('Source') }}</x-ui::table.header-cell>
                    <x-ui::table.header-cell :last="true">{{ __('Localité') }}</x-ui::table.header-cell>
                </x-ui::table.head>
                <x-ui::table.body>
                    @foreach ($sessions as $session)
                        <x-ui::table.row wire:key="session-{{ $session->id }}" class="an-row-link">
                            <x-ui::table.cell :first="true" variant="primary" class="an:whitespace-nowrap">
                                <span class="an:inline-flex an:items-center an:gap-x-2">
                                    <a href="{{ route('analytics.admin.sessions.show', $session->id) }}" class="an-row-link__target an:cursor-pointer an:hover:underline">{{ $session->startedAt->translatedFormat('d M Y, H:i') }}</a>
                                    @if ($detail->isIdentified && $session->signedIn)
                                        <x-ui::badge color="blue">{{ __('Connecté') }}</x-ui::badge>
                                    @endif
                                </span>
                            </x-ui::table.cell>
                            <x-ui::table.cell class="an:whitespace-nowrap">{{ $session->duration }}</x-ui::table.cell>
                            <x-ui::table.cell class="an:tabular-nums">{{ $session->pageviewCount }}</x-ui::table.cell>
                            <x-ui::table.cell>{{ $session->device }}</x-ui::table.cell>
                            <x-ui::table.cell>
                                <x-ui::badge color="gray"><x-analytics::source :value="$session->source" /></x-ui::badge>
                            </x-ui::table.cell>
                            <x-ui::table.cell :last="true" class="an:whitespace-nowrap">
                                @if ($session->country || $session->city)
                                    <x-analytics::country :code="$session->country" :city="$session->city" />
                                @else
                                    <span class="an:text-muted">{{ __('Inconnu') }}</span>
                                @endif
                            </x-ui::table.cell>
                        </x-ui::table.row>
                    @endforeach
                </x-ui::table.body>
            </x-ui::table>

            <div class="an:mt-6"><x-ui::pagination :paginator="$sessions" mode="livewire" /></div>
        @endif
    </div>

    {{-- Danger zone --}}
    <div class="an:pt-2">
        <x-ui::section-header :title="__('Zone de danger')" :danger="true" :description="__('L\'effacement des données de ce visiteur est définitif.')" class="an:mb-4" />

        @error('visitor-erasure-failed')
            <x-ui::alert type="error" class="an:mb-4">{{ $message }}</x-ui::alert>
        @enderror

        <div class="an:flex an:flex-col an:gap-3 an:rounded-xl an:border an:border-red-200 an:bg-red-50/40 an:px-5 an:py-4 an:dark:border-red-500/20 an:dark:bg-red-500/[0.06] an:sm:flex-row an:sm:items-center an:sm:justify-between">
            <div>
                <p class="an:text-[13px] an:font-medium an:text-primary">{{ __('Supprimer les données de ce visiteur') }}</p>
                <p class="an:mt-0.5 an:text-[12px] an:text-secondary">{{ __('Efface le visiteur, ses sessions et ses évènements. Action irréversible (droit à l\'effacement).') }}</p>
            </div>
            <x-ui::button variant="danger" class="an:shrink-0" @click="$dispatch('ui-open-modal', 'forget-visitor')">
                <x-ui::icon name="trash" class="an:h-4 an:w-4" /> {{ __('Supprimer') }}
            </x-ui::button>
        </div>
    </div>

    <x-ui::modal name="forget-visitor" variant="confirm" :title="__('Supprimer ce visiteur ?')">
        {{ __('Cette action est irréversible : le visiteur, ses :count session(s) et tous leurs évènements seront définitivement supprimés.', ['count' => $detail->sessionCount]) }}
        <x-slot:actions>
            <x-ui::button variant="ghost" @click="$dispatch('ui-close-modal', 'forget-visitor')">{{ __('Annuler') }}</x-ui::button>
            <x-ui::button variant="danger" wire:click="forget" @click="$dispatch('ui-close-modal', 'forget-visitor')">{{ __('Supprimer définitivement') }}</x-ui::button>
        </x-slot:actions>
    </x-ui::modal>
</x-analytics::root>
