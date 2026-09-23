<x-analytics::root area="admin" class="an:space-y-6">

    @include('analytics::livewire.dashboard.partials.tooltip-host')

    <div>
        <a href="{{ route('analytics.admin.sessions') }}" class="an:inline-flex an:cursor-pointer an:items-center an:gap-x-1 an:text-[12px] an:font-medium an:text-secondary an:transition-colors an:hover:text-primary">
            <x-ui::icon name="arrow-left" class="an:h-3.5 an:w-3.5" />
            {{ __('Retour aux sessions') }}
        </a>
    </div>

    {{-- Header --}}
    <div class="an:min-w-0">
        <h1 class="an:text-2xl an:font-semibold an:tracking-tight an:text-primary">
            {{ __('Session') }} #{{ $detail->id }} <span class="an:text-muted">·</span> {{ $detail->startedAt->translatedFormat('d F Y à H:i') }}
        </h1>
        <div class="an:mt-1.5 an:flex an:flex-wrap an:items-center an:gap-x-2.5 an:gap-y-1 an:text-sm an:text-secondary">
            <a href="{{ route('analytics.admin.visitors.show', $detail->visitor->id) }}" class="an:inline-flex an:cursor-pointer an:items-center an:gap-1 an:font-medium an:text-primary an:hover:underline" title="{{ __('Voir le profil du visiteur') }}">
                {{ $detail->visitor->name }}
                <x-ui::icon name="arrow-top-right-on-square" class="an:h-3 an:w-3 an:text-muted" />
            </a>
            @if ($detail->visitor->label !== null)
                <span class="an:text-muted">·</span>
                <span>{{ $detail->visitor->label }}</span>
            @endif
            @if ($detail->visitor->viaVisitor)
                <span title="{{ __('Le visiteur ne s\'est pas connecté à son compte durant cette session ; il est identifié par ses autres sessions.') }}">
                    <x-ui::badge color="gray">{{ __('Non connecté') }}</x-ui::badge>
                </span>
            @endif
            @if ($detail->visitor->isReturning)
                <x-ui::badge color="blue">{{ __('Récurrent') }}</x-ui::badge>
            @endif
            @if ($detail->visitor->uuid !== null)
                <span class="an:text-muted">·</span>
                <x-analytics::visitor-id :uuid="$detail->visitor->uuid" />
            @endif
        </div>
    </div>

    {{-- Key figures --}}
    <div class="an:grid an:grid-cols-2 an:gap-3 an:sm:grid-cols-3 an:sm:gap-4">
        <x-ui::stat-card :label="__('Durée')" :value="$detail->duration" icon="clock" />
        <x-ui::stat-card :label="__('Pages vues')" :value="(string) $detail->pageviewCount" icon="document-text" />
        <x-ui::stat-card :label="__('Clics')" :value="(string) $detail->clicksCount" icon="cursor-arrow-rays" />
        <x-ui::stat-card :label="__('Événements')" :value="(string) $detail->eventsCount" icon="bolt" />
        <x-ui::stat-card :label="__('Conversions')" :value="(string) $detail->conversionsCount" icon="check-circle" />
        <x-ui::stat-card :label="__('Durée moy. par page')" :value="$detail->averagePageDuration" icon="clock" />
    </div>

    {{-- Body: journey + details. Below lg the aside stacks, so we switch to tabs. --}}
    <div
        class="an:grid an:grid-cols-1 an:gap-6 an:lg:grid-cols-3"
        x-data="anSessionTabs"
    >
        {{-- Mobile-only tabs --}}
        <div class="an:lg:hidden">
            <div class="an:flex an:gap-1 an:rounded-lg an:bg-elevated an:p-1">
                {{-- `infos` and `parcours` are tab names, not classes; what follows the
                     question mark is. --}}
                <button type="button" @click="tab = 'infos'" :class="tab === 'infos' ? 'an:bg-surface an:text-primary' : 'an:text-secondary an:hover:text-primary'" class="an:flex-1 an:cursor-pointer an:rounded-lg an:px-3 an:py-1.5 an:text-[13px] an:font-medium an:transition-colors">{{ __('Infos') }}</button>
                <button type="button" @click="tab = 'parcours'" :class="tab === 'parcours' ? 'an:bg-surface an:text-primary' : 'an:text-secondary an:hover:text-primary'" class="an:flex-1 an:cursor-pointer an:rounded-lg an:px-3 an:py-1.5 an:text-[13px] an:font-medium an:transition-colors">{{ __('Parcours') }}</button>
            </div>
        </div>

        {{-- Journey --}}
        <div class="an:min-w-0 an:lg:col-span-2" x-show="desktop || tab === 'parcours'">
            <x-ui::card>
                <x-ui::section-header :title="__('Parcours')" :description="__('Ce que le visiteur a fait, dans l\'ordre')" class="an:mb-5" />

                {{--
                    Two absences, and they do not read the same: a session that
                    recorded nothing, and one whose step-by-step the retention
                    erased. Saying "no event recorded" of the second is
                    contradicted by the figures just above it.
                --}}
                @if (empty($detail->journey) && $detail->detailErased)
                    <x-ui::empty-state
                        icon="archive-box"
                        :title="__('Détail effacé')"
                        :description="__('Le pas à pas de cette session a été effacé, sa journée étant sortie de la durée de conservation. Les chiffres ci-dessus, eux, restent ceux de la session.')" />
                @elseif (empty($detail->journey))
                    <x-ui::empty-state icon="signal" :title="__('Aucun événement')" :description="__('Cette session n\'a enregistré aucun événement.')" />
                @else
                    @if ($detail->detailErased)
                        <x-ui::alert type="info" class="an:mb-5">
                            {{ __('Une partie du pas à pas a été effacée : la journée de cette session est sortie de la durée de conservation, et les pages vues et les clics qui ne portent pas de nom y sont effacés. Les chiffres ci-dessus, eux, restent ceux de la session entière.') }}
                        </x-ui::alert>
                    @endif
                    <ol class="an:relative">
                        @foreach ($detail->journey as $step)
                            <li class="an:relative an:flex an:gap-4 an:pb-6 an:last:pb-0">
                                @unless ($loop->last)
                                    <span class="an:absolute an:bottom-0 an:left-4 an:top-8 an:w-px an:bg-default"></span>
                                @endunless
                                <span class="an:relative an:z-10 an:flex an:h-8 an:w-8 an:shrink-0 an:items-center an:justify-center an:rounded-full an:bg-elevated an:ring-4 an:ring-surface">
                                    <x-ui::icon :name="$step->isPageview ? 'document-text' : ($step->isConversion ? 'bolt' : 'cursor-arrow-rays')" @class(['an:h-4 an:w-4', 'an:text-emerald-500' => $step->isConversion, 'an:text-secondary' => ! $step->isConversion]) />
                                </span>
                                <div class="an:min-w-0 an:flex-1 an:pt-1">
                                    <div class="an:flex an:items-baseline an:justify-between an:gap-2">
                                        <p @class(['an:flex an:min-w-0 an:items-center an:gap-2 an:text-[13px] an:font-medium', 'an:text-emerald-600 an:dark:text-emerald-400' => $step->isConversion, 'an:text-primary' => ! $step->isConversion])>
                                            <span class="an:min-w-0 an:truncate">@if ($step->isPageview)<x-analytics::page-url :route="$step->route" :url="$step->url" />@else{{ $step->label }}@endif</span>
                                            @if ($step->isPageview && $loop->first)
                                                <x-ui::badge color="gray">{{ __('Entrée') }}</x-ui::badge>
                                            @elseif ($step->isPageview && $loop->last)
                                                <x-ui::badge color="gray">{{ __('Sortie') }}</x-ui::badge>
                                            @endif
                                        </p>
                                        <span class="an:shrink-0 an:text-[11px] an:tabular-nums an:text-muted">{{ $step->occurredAt->translatedFormat('H:i:s') }}</span>
                                    </div>

                                    @if ($step->isPageview)
                                        <div class="an:mt-1.5 an:flex an:items-center an:gap-2">
                                            <div class="an:h-1 an:flex-1 an:overflow-hidden an:rounded-full an:bg-elevated">
                                                <div class="an:h-full an:rounded-full an:bg-series-1" style="width: {{ $step->barPercent }}%"></div>
                                            </div>
                                            <span class="an:w-14 an:shrink-0 an:text-right an:text-[11px] an:tabular-nums an:text-muted">{{ $step->duration }}</span>
                                        </div>
                                    @endif

                                    @if (! empty($step->children))
                                        <div class="an:mt-2.5 an:space-y-1.5">
                                            @foreach ($step->children as $child)
                                                <div class="an:flex an:items-center an:gap-2">
                                                    <x-ui::icon :name="$child->isConversion ? 'bolt' : 'cursor-arrow-rays'" @class(['an:h-3.5 an:w-3.5 an:shrink-0', 'an:text-emerald-500' => $child->isConversion, 'an:text-series-1' => ! $child->isConversion]) />
                                                    <span @class(['an:min-w-0 an:truncate an:text-[12px]', 'an:font-medium an:text-emerald-600 an:dark:text-emerald-400' => $child->isConversion, 'an:text-secondary' => ! $child->isConversion]) data-an-tooltip="{{ $child->label }}">{{ $child->label }}</span>
                                                    <span class="an:ml-auto an:shrink-0 an:text-[11px] an:tabular-nums an:text-muted">{{ $child->occurredAt->translatedFormat('H:i:s') }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-ui::card>
        </div>

        {{-- Details --}}
        <div class="an:min-w-0 an:space-y-5" x-show="desktop || tab === 'infos'">

            <x-ui::card>
                <x-ui::section-header :title="__('Acquisition')" class="an:mb-3" />
                <div class="an:flex an:items-center an:gap-3 an:border-b an:border-subtle an:pb-3">
                    <span class="an:flex an:h-9 an:w-9 an:shrink-0 an:items-center an:justify-center an:rounded-lg an:bg-elevated an:text-secondary">
                        <x-ui::icon :name="$detail->acquisition->icon" class="an:h-4 an:w-4" />
                    </span>
                    <div class="an:min-w-0 an:flex-1">
                        <p class="an:truncate an:text-[13px] an:font-semibold an:text-primary">
                            <x-analytics::source :value="$detail->acquisition->source" />
                        </p>
                        <p class="an:truncate an:text-[11px] an:text-muted">{{ $detail->acquisition->description }}</p>
                    </div>
                </div>
                <dl class="an:mt-3 an:space-y-2.5">
                    @if ($detail->acquisition->searchQuery !== null)
                        <x-analytics::detail-row :label="__('Recherche')" :value="$detail->acquisition->searchQuery" icon="magnifying-glass" />
                    @endif
                    @if ($detail->acquisition->campaignTerm !== null)
                        <x-analytics::detail-row label="utm_term" :value="$detail->acquisition->campaignTerm" icon="tag" />
                    @endif
                    <x-analytics::detail-row :label="__('Page d\'entrée')" icon="document-text">@if ($detail->acquisition->landingRoute || $detail->acquisition->landingUrl)<x-analytics::page-url :route="$detail->acquisition->landingRoute" :url="$detail->acquisition->landingUrl" />@endif</x-analytics::detail-row>
                    <x-analytics::detail-row :label="__('Référent')" :value="$detail->acquisition->referrer" icon="arrow-top-right-on-square" />
                    @foreach ($detail->acquisition->campaignParameters as $utmLabel => $utmValue)
                        <x-analytics::detail-row :label="$utmLabel" :value="$utmValue" icon="tag" />
                    @endforeach
                </dl>
            </x-ui::card>

            <x-ui::card>
                <x-ui::section-header :title="__('Répartition de la durée')" :description="__('Par page')" class="an:mb-4" />
                @if ($detail->timeTotal !== null)
                    <div class="an:flex an:items-center an:gap-5">
                        <div wire:key="donut-time-{{ $detail->id }}">
                            <x-analytics::donut
                                :labels="array_column($detail->timeShares, 'label')"
                                :values="array_column($detail->timeShares, 'seconds')"
                                :colors="array_column($detail->timeShares, 'color')"
                                :total="$detail->timeTotal"
                                :caption="__('total')"
                                size="an:h-24 an:w-24" />
                        </div>
                        <div class="an:min-w-0 an:flex-1 an:space-y-2">
                            @foreach ($detail->timeShares as $share)
                                <div class="an:flex an:items-center an:gap-2">
                                    <span class="an:h-2 an:w-2 an:shrink-0 an:rounded-full" style="background: var({{ $share->color }})"></span>
                                    <span class="an:min-w-0 an:flex-1 an:truncate an:text-[12px] an:text-secondary">{{ $share->label }}</span>
                                    <span class="an:shrink-0 an:text-[12px] an:font-medium an:text-primary">{{ $share->duration }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <p class="an:text-[12px] an:text-muted">{{ __('Durée par page indisponible.') }}</p>
                @endif
            </x-ui::card>

            <x-ui::card>
                <x-ui::section-header :title="__('Localité')" class="an:mb-3" />
                <dl class="an:space-y-2.5">
                    <x-analytics::detail-row :label="__('Pays')" icon="flag">@if ($detail->country)<x-analytics::country :code="$detail->country" />@endif</x-analytics::detail-row>
                    <x-analytics::detail-row :label="__('Ville')" :value="$detail->city" icon="map-pin" />
                    <x-analytics::detail-row :label="__('IP')" :value="$detail->ip" icon="hashtag" mono />
                </dl>
            </x-ui::card>

            <x-ui::card>
                <x-ui::section-header :title="__('Appareil')" class="an:mb-3">
                    <x-ui::icon :name="$detail->deviceIcon" class="an:h-4 an:w-4 an:text-muted" />
                </x-ui::section-header>
                <dl class="an:space-y-2.5">
                    <x-analytics::detail-row :label="__('Type')" :value="$detail->deviceLabel" :icon="$detail->deviceIcon" />
                    <x-analytics::detail-row :label="__('Navigateur')" :value="$detail->browser" icon="globe-alt" />
                    <x-analytics::detail-row :label="__('Système')" :value="$detail->system" icon="cpu-chip" />
                </dl>
            </x-ui::card>

        </div>
    </div>

</x-analytics::root>
