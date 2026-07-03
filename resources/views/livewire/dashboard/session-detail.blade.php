@php
    use Falcon\Analytics\Enums\EventType;
    use Falcon\Analytics\Support\DeviceLabel;
    use Illuminate\Support\Str;

    $routeName = config('analytics.dashboard.route_name', 'analytics');
    $value = fn ($raw) => filled($raw) ? $raw : null;
    $visitorLabel = $subjectLabel;
    $visitorName = $subjectName;
    $visitorPrimary = $visitorName ?? ($session->subject_type ? $visitorLabel.' #'.$session->subject_id : __('Visiteur anonyme'));

    $formatSeconds = function (int $seconds): string {
        $minutes = intdiv($seconds, 60);
        $rest = $seconds % 60;

        if ($minutes > 0) {
            return $rest > 0 ? "{$minutes}\u{00A0}min\u{00A0}{$rest}\u{00A0}s" : "{$minutes}\u{00A0}min";
        }

        return "{$seconds}\u{00A0}s";
    };

    $eventLabel = fn ($event) => $value($event->target_text) ?? $value($event->name)
        ?? ($event->type === EventType::Click ? __('Clic') : __('Évènement'));

    $seconds = (int) $session->started_at->diffInSeconds($session->last_activity_at);
    $duration = $formatSeconds($seconds);
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
    $sourceDescription = [
        'direct' => 'Accès direct',
        'organic' => 'Recherche naturelle',
        'social' => 'Réseaux sociaux',
        'paid' => 'Trafic publicitaire',
        'referral' => 'Site référent',
        'email' => 'Campagne e-mail',
        'campaign' => 'Campagne balisée',
    ][$sourceKey] ?? 'Provenance inconnue';

    // Search keyword: UTM term, or the query of a search-engine referrer.
    $searchKeyword = $value($session->utm_term);
    if ($searchKeyword === null && filled($session->referrer)) {
        parse_str((string) (parse_url($session->referrer, PHP_URL_QUERY) ?: ''), $refParams);
        $searchKeyword = $value($refParams['q'] ?? ($refParams['query'] ?? null));
    }

    $utm = collect([
        __('Campagne') => $session->utm_campaign,
        __('Source UTM') => $session->utm_source,
        __('Support') => $session->utm_medium,
        __('Contenu') => $session->utm_content,
    ])->filter(fn ($v) => filled($v));

    // Time distribution donut (top pages + others).
    $palette = ['#1684ea', '#4b9bf0', '#7cb8f2', '#a5cdf7', '#bcdcfa', '#d1d5db'];
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

<div class="space-y-6">

    @include('analytics::livewire.dashboard.partials.tooltip-host')

    <div>
        <a href="{{ route($routeName.'.sessions') }}" class="inline-flex cursor-pointer items-center gap-x-1 text-[12px] font-medium text-secondary transition-colors hover:text-primary">
            <x-ui.icon name="arrow-left" class="h-3.5 w-3.5" />
            {{ __('Retour aux sessions') }}
        </a>
    </div>

    {{-- Header --}}
    <div class="min-w-0">
        <h1 class="text-2xl font-semibold tracking-tight text-primary">
            {{ __('Session') }} #{{ $session->id }} <span class="text-muted">·</span> {{ $session->started_at->translatedFormat('d F Y à H:i') }}
        </h1>
        <div class="mt-1.5 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-sm text-secondary">
            <span class="font-medium text-primary">{{ $visitorPrimary }}</span>
            @if ($visitorName && $visitorLabel)
                <span class="text-muted">·</span>
                <span>{{ $visitorLabel }}</span>
            @endif
            @if ($isReturning)
                <x-ui.badge color="blue">{{ __('Récurrent') }}</x-ui.badge>
            @endif
            @if ($session->visitor?->uuid)
                <span class="text-muted">·</span>
                <span>{{ __('ID') }}{{ "\u{00A0}" }}: {{ Str::limit($session->visitor->uuid, 24, '…') }}</span>
            @endif
        </div>
    </div>

    {{-- Key figures --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
        <x-ui.stat-card :label="__('Durée')" :value="$duration" icon="clock" />
        <x-ui.stat-card :label="__('Pages vues')" :value="(string) $session->pageview_count" icon="document-text" />
        <x-ui.stat-card :label="__('Clics')" :value="(string) $clicksCount" icon="cursor-arrow-rays" />
        <x-ui.stat-card :label="__('Temps moy./page')" :value="$formatSeconds($avgPageSeconds)" icon="clock" />
    </div>

    {{-- Body: journey + details. Below lg the aside stacks, so we switch to tabs. --}}
    <div
        class="grid grid-cols-1 gap-6 lg:grid-cols-3"
        x-data="{
            tab: 'infos',
            desktop: window.matchMedia('(min-width: 1024px)').matches,
            init() {
                window.matchMedia('(min-width: 1024px)').addEventListener('change', (e) => { this.desktop = e.matches; });
            },
        }"
    >
        {{-- Mobile-only tabs --}}
        <div class="lg:hidden">
            <div class="flex gap-1 rounded-lg bg-elevated p-1">
                <button type="button" @click="tab = 'infos'" :class="tab === 'infos' ? 'bg-surface text-primary' : 'text-secondary hover:text-primary'" class="flex-1 cursor-pointer rounded-md px-3 py-1.5 text-[13px] font-medium transition-colors">{{ __('Infos') }}</button>
                <button type="button" @click="tab = 'parcours'" :class="tab === 'parcours' ? 'bg-surface text-primary' : 'text-secondary hover:text-primary'" class="flex-1 cursor-pointer rounded-md px-3 py-1.5 text-[13px] font-medium transition-colors">{{ __('Parcours') }}</button>
            </div>
        </div>

        {{-- Journey --}}
        <div class="min-w-0 lg:col-span-2" x-show="desktop || tab === 'parcours'">
            <x-ui.card>
                <x-ui.section-header :title="__('Parcours')" :description="__('Ce que le visiteur a fait, dans l\'ordre')" class="mb-5" />

                @if (empty($journey))
                    <x-ui.empty-state icon="signal" :title="__('Aucun évènement')" :description="__('Cette session n\'a enregistré aucun évènement.')" />
                @else
                    <ol class="relative">
                        @foreach ($journey as $step)
                            @php
                                $event = $step['event'];
                                $isPageview = $event->type === EventType::Pageview;
                                $isConversionStep = $event->type === EventType::Custom;
                                $barPct = $isPageview ? max(3, (int) round($step['seconds'] / $maxStepSeconds * 100)) : 0;
                            @endphp
                            <li class="relative flex gap-4 pb-6 last:pb-0">
                                @unless ($loop->last)
                                    <span class="absolute bottom-0 left-4 top-8 w-px bg-base"></span>
                                @endunless
                                <span class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-elevated ring-4 ring-surface">
                                    <x-ui.icon :name="$isPageview ? 'document-text' : ($isConversionStep ? 'bolt' : 'cursor-arrow-rays')" @class(['h-4 w-4', 'text-emerald-500' => $isConversionStep, 'text-secondary' => ! $isConversionStep]) />
                                </span>
                                <div class="min-w-0 flex-1 pt-1">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <p @class(['flex min-w-0 items-center gap-2 text-[13px] font-medium', 'text-emerald-600 dark:text-emerald-400' => $isConversionStep, 'text-primary' => ! $isConversionStep])>
                                            <span class="min-w-0 truncate">@if ($isPageview)<x-analytics::page-url :route="$event->route" :url="$event->url" />@else{{ $eventLabel($event) }}@endif</span>
                                            @if ($isPageview && $loop->first)
                                                <x-ui.badge color="gray">{{ __('Entrée') }}</x-ui.badge>
                                            @elseif ($isPageview && $loop->last)
                                                <x-ui.badge color="gray">{{ __('Sortie') }}</x-ui.badge>
                                            @endif
                                        </p>
                                        <span class="shrink-0 text-[11px] tabular-nums text-muted">{{ $event->occurred_at->translatedFormat('H:i:s') }}</span>
                                    </div>

                                    @if ($isPageview)
                                        <div class="mt-1.5 flex items-center gap-2">
                                            <div class="h-1 flex-1 overflow-hidden rounded-full bg-elevated">
                                                <div class="h-full rounded-full bg-[#1684ea]" style="width: {{ $barPct }}%"></div>
                                            </div>
                                            <span class="w-14 shrink-0 text-right text-[11px] tabular-nums text-muted">{{ $formatSeconds($step['seconds']) }}</span>
                                        </div>
                                    @endif

                                    @if (! empty($step['children']))
                                        <div class="mt-2.5 space-y-1.5">
                                            @foreach ($step['children'] as $child)
                                                @php $isConversion = $child->type === EventType::Custom; @endphp
                                                <div class="flex items-center gap-2">
                                                    <x-ui.icon :name="$isConversion ? 'bolt' : 'cursor-arrow-rays'" @class(['h-3.5 w-3.5 shrink-0', 'text-emerald-500' => $isConversion, 'text-[#1684ea]' => ! $isConversion]) />
                                                    @php $childLabel = $eventLabel($child); @endphp
                                                    <span @class(['min-w-0 truncate text-[12px]', 'font-medium text-emerald-600 dark:text-emerald-400' => $isConversion, 'text-secondary' => ! $isConversion]) data-tooltip="{{ $childLabel }}">{{ $childLabel }}</span>
                                                    <span class="ml-auto shrink-0 text-[11px] tabular-nums text-muted">{{ $child->occurred_at->translatedFormat('H:i:s') }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-ui.card>
        </div>

        {{-- Details --}}
        <div class="min-w-0 space-y-5" x-show="desktop || tab === 'infos'">

            <x-ui.card>
                <x-ui.section-header :title="__('Acquisition')" class="mb-3" />
                <div class="flex items-center gap-3 border-b border-subtle pb-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-elevated text-secondary">
                        <x-ui.icon :name="$sourceIcon" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[13px] font-semibold text-primary">
                            @if ($session->source)<x-analytics::source :value="$session->source" />@else{{ __('Direct') }}@endif
                        </p>
                        <p class="truncate text-[11px] text-muted">{{ __($sourceDescription) }}</p>
                    </div>
                </div>
                <dl class="mt-3 space-y-2.5">
                    @if ($searchKeyword)
                        <x-analytics::detail-row :label="__('Mot-clé')" :value="$searchKeyword" icon="magnifying-glass" />
                    @endif
                    <x-analytics::detail-row :label="__('Page d\'entrée')" icon="document-text">@if ($session->landing_route || $session->landing_url)<x-analytics::page-url :route="$session->landing_route" :url="$session->landing_url" />@endif</x-analytics::detail-row>
                    <x-analytics::detail-row :label="__('Référent')" :value="$value($session->referrer)" icon="arrow-top-right-on-square" />
                    @foreach ($utm as $utmLabel => $utmValue)
                        <x-analytics::detail-row :label="$utmLabel" :value="$utmValue" icon="tag" />
                    @endforeach
                </dl>
            </x-ui.card>

            <x-ui.card>
                <x-ui.section-header :title="__('Répartition du temps')" :description="__('Par page')" class="mb-4" />
                @if ($totalPageSeconds > 0)
                    <div class="flex items-center gap-5">
                        <div wire:key="donut-time-{{ $session->id }}">
                            <x-analytics::donut
                                :labels="array_keys($segments)"
                                :values="array_values($segments)"
                                :colors="array_slice($palette, 0, count($segments))"
                                :total="$formatSeconds($totalPageSeconds)"
                                :caption="__('total')"
                                size="h-24 w-24" />
                        </div>
                        <div class="min-w-0 flex-1 space-y-2">
                            @foreach ($segments as $pageLabel => $pageSeconds)
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $palette[$loop->index] ?? '#d1d5db' }}"></span>
                                    <span class="min-w-0 flex-1 truncate text-[12px] text-secondary">{{ $pageLabel }}</span>
                                    <span class="shrink-0 text-[12px] font-medium text-primary">{{ $formatSeconds($pageSeconds) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <p class="text-[12px] text-muted">{{ __('Temps par page indisponible.') }}</p>
                @endif
            </x-ui.card>

            <x-ui.card>
                <x-ui.section-header :title="__('Localité')" class="mb-3" />
                <dl class="space-y-2.5">
                    <x-analytics::detail-row :label="__('Pays')" icon="flag">@if ($session->country)<x-analytics::country :code="$session->country" />@endif</x-analytics::detail-row>
                    <x-analytics::detail-row :label="__('Ville')" :value="$value($session->city)" icon="map-pin" />
                    <x-analytics::detail-row :label="__('IP')" :value="$value($session->ip)" icon="hashtag" mono />
                </dl>
            </x-ui.card>

            <x-ui.card>
                <x-ui.section-header :title="__('Appareil')" class="mb-3">
                    <x-ui.icon :name="$deviceIcon" class="h-4 w-4 text-muted" />
                </x-ui.section-header>
                <dl class="space-y-2.5">
                    <x-analytics::detail-row :label="__('Type')" :value="$session->device_type ? DeviceLabel::for($session->device_type) : null" :icon="$deviceIcon" />
                    <x-analytics::detail-row :label="__('Navigateur')" :value="trim(($session->browser ?? '').' '.($session->browser_version ?? '')) ?: null" icon="globe-alt" />
                    <x-analytics::detail-row :label="__('Système')" :value="trim(($session->os ?? '').' '.($session->os_version ?? '')) ?: null" icon="cpu-chip" />
                </dl>
            </x-ui.card>

        </div>
    </div>

</div>
