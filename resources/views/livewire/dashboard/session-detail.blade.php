@php
    use Falcon\Analytics\Enums\EventType;
    use Falcon\Analytics\Support\ChartPalette;
    use Falcon\Analytics\Support\DeviceLabel;
    use Falcon\Analytics\Support\DurationLabel;
    use Falcon\Analytics\Support\SourceLabel;

    $value = fn ($raw) => filled($raw) ? $raw : null;
    $visitorLabel = $subjectLabel;
    $visitorName = $subjectName;
    $visitorPrimary = $visitorName ?? ($visitorLabel !== null ? $visitorLabel.' #'.$subjectId : __('Visiteur anonyme'));

    $eventLabel = fn ($event) => $value($event->target_text) ?? $value($event->name)
        ?? ($event->type === EventType::Click ? __('Clic') : __('Évènement'));

    $seconds = (int) $session->started_at->diffInSeconds($session->last_activity_at);
    $duration = DurationLabel::for($seconds);
    $avgPageSeconds = $session->pageview_count > 0 ? (int) round($seconds / $session->pageview_count) : 0;

    $deviceIcon = match (strtolower((string) $session->device_type)) {
        'mobile' => 'device-phone-mobile',
        'tablet' => 'device-tablet',
        'desktop' => 'computer-desktop',
        default => 'question-mark-circle',
    };

    $isReturning = ((int) ($session->visitor?->session_count ?? 1)) > 1;

    // Acquisition: a prominent, self-explanatory channel with its icon.
    $sourceKey = strtolower((string) $session->source);
    $sourceIcon = [
        'direct' => 'cursor-arrow-rays',
        'organic' => 'magnifying-glass',
        'social' => 'user-group',
        'paid' => 'megaphone',
        'referral' => 'arrow-top-right-on-square',
        'email' => 'envelope',
        'campaign' => 'flag',
    ][$sourceKey] ?? 'globe-alt';

    // A real search query is almost never available on modern engines (they strip it
    // from the referrer for privacy). utm_term is an advertiser-set campaign
    // parameter (often a numeric keyword/ad id, not the user's query) so it is kept
    // distinct from a genuine search term rather than mislabelled as a keyword.
    $searchQuery = null;
    if (filled($session->referrer)) {
        parse_str((string) (parse_url($session->referrer, PHP_URL_QUERY) ?: ''), $refParams);
        $searchQuery = $value($refParams['q'] ?? ($refParams['query'] ?? null));
    }
    $campaignTerm = $value($session->utm_term);

    $utm = collect([
        __('Campagne') => $session->utm_campaign,
        __('Source UTM') => $session->utm_source,
        __('Support') => $session->utm_medium,
        __('Contenu') => $session->utm_content,
    ])->filter(fn ($v) => filled($v));

    // Time distribution donut (top pages + others).
    $palette = ChartPalette::SERIES;
    $totalPageSeconds = array_sum($timePerPage);
    $segments = array_slice($timePerPage, 0, 5, true);
    $othersSeconds = array_sum(array_slice($timePerPage, 5, null, true));
    if ($othersSeconds > 0) {
        $segments[__('Autres')] = $othersSeconds;
    }

    $maxStepSeconds = 1;
    foreach ($journey as $s) {
        if ($s['event']->type === EventType::Pageview) {
            $maxStepSeconds = max($maxStepSeconds, $s['seconds']);
        }
    }
@endphp

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
            {{ __('Session') }} #{{ $session->id }} <span class="an:text-muted">·</span> {{ $session->started_at->translatedFormat('d F Y à H:i') }}
        </h1>
        <div class="an:mt-1.5 an:flex an:flex-wrap an:items-center an:gap-x-2.5 an:gap-y-1 an:text-sm an:text-secondary">
            <a href="{{ route('analytics.admin.visitors.show', $session->visitor_id) }}" class="an:inline-flex an:cursor-pointer an:items-center an:gap-1 an:font-medium an:text-primary an:hover:underline" title="{{ __('Voir le profil du visiteur') }}">
                {{ $visitorPrimary }}
                <x-ui::icon name="arrow-top-right-on-square" class="an:h-3 an:w-3 an:text-muted" />
            </a>
            @if ($visitorName && $visitorLabel)
                <span class="an:text-muted">·</span>
                <span>{{ $visitorLabel }}</span>
            @endif
            @if ($subjectViaVisitor)
                <span title="{{ __('Le visiteur ne s\'est pas connecté à son compte durant cette session ; il est identifié par ses autres sessions.') }}">
                    <x-ui::badge color="gray">{{ __('Non connecté') }}</x-ui::badge>
                </span>
            @endif
            @if ($isReturning)
                <x-ui::badge color="blue">{{ __('Récurrent') }}</x-ui::badge>
            @endif
            @if ($session->visitor?->uuid)
                <span class="an:text-muted">·</span>
                <x-analytics::visitor-id :uuid="$session->visitor->uuid" />
            @endif
        </div>
    </div>

    {{-- Key figures --}}
    <div class="an:grid an:grid-cols-2 an:gap-3 an:sm:grid-cols-3 an:sm:gap-4">
        <x-ui::stat-card :label="__('Durée')" :value="$duration" icon="clock" />
        <x-ui::stat-card :label="__('Pages vues')" :value="(string) $session->pageview_count" icon="document-text" />
        <x-ui::stat-card :label="__('Clics')" :value="(string) $clicksCount" icon="cursor-arrow-rays" />
        <x-ui::stat-card :label="__('Événements')" :value="(string) $eventsCount" icon="bolt" />
        <x-ui::stat-card :label="__('Conversions')" :value="(string) $conversionsCount" icon="check-circle" />
        <x-ui::stat-card :label="__('Temps moy./page')" :value="DurationLabel::for($avgPageSeconds)" icon="clock" />
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
                    Deux absences, et elles ne se disent pas pareil.

                    Un parcours vide parce que la session n'a rien enregistré,
                    et un parcours vide parce que la conservation est passée
                    dessus. Dire « cette session n'a enregistré aucun
                    évènement » d'une session qui en avait quatre est un
                    mensonge que le paquet se raconte tout seul · les chiffres
                    juste au-dessus le contredisent à l'écran.
                --}}
                @if (empty($journey) && $detailErased)
                    <x-ui::empty-state
                        icon="archive-box"
                        :title="__('Détail effacé')"
                        :description="__('Le pas à pas de cette session a été effacé, sa journée étant sortie de la durée de conservation. Les chiffres ci-dessus, eux, restent ceux de la visite.')" />
                @elseif (empty($journey))
                    <x-ui::empty-state icon="signal" :title="__('Aucun évènement')" :description="__('Cette session n\'a enregistré aucun évènement.')" />
                @else
                    @if ($detailErased)
                        <x-ui::alert type="info" class="an:mb-5">
                            {{ __('Une partie du pas à pas a été effacée : la journée de cette session est sortie de la durée de conservation, et les pages vues et les clics qui ne portent pas de nom y sont effacés. Les chiffres ci-dessus, eux, restent ceux de la visite entière.') }}
                        </x-ui::alert>
                    @endif
                    <ol class="an:relative">
                        @foreach ($journey as $step)
                            @php
                                $event = $step['event'];
                                $isPageview = $event->type === EventType::Pageview;
                                $isConversionStep = $event->type === EventType::Custom;
                                $barPct = $isPageview ? max(3, (int) round($step['seconds'] / $maxStepSeconds * 100)) : 0;
                            @endphp
                            <li class="an:relative an:flex an:gap-4 an:pb-6 an:last:pb-0">
                                @unless ($loop->last)
                                    <span class="an:absolute an:bottom-0 an:left-4 an:top-8 an:w-px an:bg-default"></span>
                                @endunless
                                <span class="an:relative an:z-10 an:flex an:h-8 an:w-8 an:shrink-0 an:items-center an:justify-center an:rounded-full an:bg-elevated an:ring-4 an:ring-surface">
                                    <x-ui::icon :name="$isPageview ? 'document-text' : ($isConversionStep ? 'bolt' : 'cursor-arrow-rays')" @class(['an:h-4 an:w-4', 'an:text-emerald-500' => $isConversionStep, 'an:text-secondary' => ! $isConversionStep]) />
                                </span>
                                <div class="an:min-w-0 an:flex-1 an:pt-1">
                                    <div class="an:flex an:items-baseline an:justify-between an:gap-2">
                                        <p @class(['an:flex an:min-w-0 an:items-center an:gap-2 an:text-[13px] an:font-medium', 'an:text-emerald-600 an:dark:text-emerald-400' => $isConversionStep, 'an:text-primary' => ! $isConversionStep])>
                                            <span class="an:min-w-0 an:truncate">@if ($isPageview)<x-analytics::page-url :route="$event->route" :url="$event->url" />@else{{ $eventLabel($event) }}@endif</span>
                                            @if ($isPageview && $loop->first)
                                                <x-ui::badge color="gray">{{ __('Entrée') }}</x-ui::badge>
                                            @elseif ($isPageview && $loop->last)
                                                <x-ui::badge color="gray">{{ __('Sortie') }}</x-ui::badge>
                                            @endif
                                        </p>
                                        <span class="an:shrink-0 an:text-[11px] an:tabular-nums an:text-muted">{{ $event->occurred_at->translatedFormat('H:i:s') }}</span>
                                    </div>

                                    @if ($isPageview)
                                        <div class="an:mt-1.5 an:flex an:items-center an:gap-2">
                                            <div class="an:h-1 an:flex-1 an:overflow-hidden an:rounded-full an:bg-elevated">
                                                <div class="an:h-full an:rounded-full an:bg-series-1" style="width: {{ $barPct }}%"></div>
                                            </div>
                                            <span class="an:w-14 an:shrink-0 an:text-right an:text-[11px] an:tabular-nums an:text-muted">{{ DurationLabel::for($step['seconds']) }}</span>
                                        </div>
                                    @endif

                                    @if (! empty($step['children']))
                                        <div class="an:mt-2.5 an:space-y-1.5">
                                            @foreach ($step['children'] as $child)
                                                @php $isConversion = $child->type === EventType::Custom; @endphp
                                                <div class="an:flex an:items-center an:gap-2">
                                                    <x-ui::icon :name="$isConversion ? 'bolt' : 'cursor-arrow-rays'" @class(['an:h-3.5 an:w-3.5 an:shrink-0', 'an:text-emerald-500' => $isConversion, 'an:text-series-1' => ! $isConversion]) />
                                                    @php $childLabel = $eventLabel($child); @endphp
                                                    <span @class(['an:min-w-0 an:truncate an:text-[12px]', 'an:font-medium an:text-emerald-600 an:dark:text-emerald-400' => $isConversion, 'an:text-secondary' => ! $isConversion]) data-an-tooltip="{{ $childLabel }}">{{ $childLabel }}</span>
                                                    <span class="an:ml-auto an:shrink-0 an:text-[11px] an:tabular-nums an:text-muted">{{ $child->occurred_at->translatedFormat('H:i:s') }}</span>
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
                        <x-ui::icon :name="$sourceIcon" class="an:h-4 an:w-4" />
                    </span>
                    <div class="an:min-w-0 an:flex-1">
                        <p class="an:truncate an:text-[13px] an:font-semibold an:text-primary">
                            <x-analytics::source :value="$session->source" />
                        </p>
                        <p class="an:truncate an:text-[11px] an:text-muted">{{ SourceLabel::description($session->source) }}</p>
                    </div>
                </div>
                <dl class="an:mt-3 an:space-y-2.5">
                    @if ($searchQuery)
                        <x-analytics::detail-row :label="__('Terme de recherche')" :value="$searchQuery" icon="magnifying-glass" />
                    @endif
                    @if ($campaignTerm)
                        <x-analytics::detail-row :label="__('Terme de campagne (utm_term)')" :value="$campaignTerm" icon="tag" />
                    @endif
                    <x-analytics::detail-row :label="__('Page d\'entrée')" icon="document-text">@if ($session->landing_route || $session->landing_url)<x-analytics::page-url :route="$session->landing_route" :url="$session->landing_url" />@endif</x-analytics::detail-row>
                    <x-analytics::detail-row :label="__('Référent')" :value="$value($session->referrer)" icon="arrow-top-right-on-square" />
                    @foreach ($utm as $utmLabel => $utmValue)
                        <x-analytics::detail-row :label="$utmLabel" :value="$utmValue" icon="tag" />
                    @endforeach
                </dl>
            </x-ui::card>

            <x-ui::card>
                <x-ui::section-header :title="__('Répartition du temps')" :description="__('Par page')" class="an:mb-4" />
                @if ($totalPageSeconds > 0)
                    <div class="an:flex an:items-center an:gap-5">
                        <div wire:key="donut-time-{{ $session->id }}">
                            <x-analytics::donut
                                :labels="array_keys($segments)"
                                :values="array_values($segments)"
                                :colors="array_slice($palette, 0, count($segments))"
                                :total="DurationLabel::for($totalPageSeconds)"
                                :caption="__('total')"
                                size="an:h-24 an:w-24" />
                        </div>
                        <div class="an:min-w-0 an:flex-1 an:space-y-2">
                            @foreach ($segments as $pageLabel => $pageSeconds)
                                <div class="an:flex an:items-center an:gap-2">
                                    <span class="an:h-2 an:w-2 an:shrink-0 an:rounded-full" style="background: var({{ $palette[$loop->index] ?? '--an-series-6' }})"></span>
                                    <span class="an:min-w-0 an:flex-1 an:truncate an:text-[12px] an:text-secondary">{{ $pageLabel }}</span>
                                    <span class="an:shrink-0 an:text-[12px] an:font-medium an:text-primary">{{ DurationLabel::for($pageSeconds) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <p class="an:text-[12px] an:text-muted">{{ __('Temps par page indisponible.') }}</p>
                @endif
            </x-ui::card>

            <x-ui::card>
                <x-ui::section-header :title="__('Localité')" class="an:mb-3" />
                <dl class="an:space-y-2.5">
                    <x-analytics::detail-row :label="__('Pays')" icon="flag">@if ($session->country)<x-analytics::country :code="$session->country" />@endif</x-analytics::detail-row>
                    <x-analytics::detail-row :label="__('Ville')" :value="$value($session->city)" icon="map-pin" />
                    <x-analytics::detail-row :label="__('IP')" :value="$value($session->ip)" icon="hashtag" mono />
                </dl>
            </x-ui::card>

            <x-ui::card>
                <x-ui::section-header :title="__('Appareil')" class="an:mb-3">
                    <x-ui::icon :name="$deviceIcon" class="an:h-4 an:w-4 an:text-muted" />
                </x-ui::section-header>
                <dl class="an:space-y-2.5">
                    <x-analytics::detail-row :label="__('Type')" :value="$session->device_type ? DeviceLabel::for($session->device_type) : null" :icon="$deviceIcon" />
                    <x-analytics::detail-row :label="__('Navigateur')" :value="trim(($session->browser ?? '').' '.($session->browser_version ?? '')) ?: null" icon="globe-alt" />
                    <x-analytics::detail-row :label="__('Système')" :value="trim(($session->os ?? '').' '.($session->os_version ?? '')) ?: null" icon="cpu-chip" />
                </dl>
            </x-ui::card>

        </div>
    </div>

</x-analytics::root>
