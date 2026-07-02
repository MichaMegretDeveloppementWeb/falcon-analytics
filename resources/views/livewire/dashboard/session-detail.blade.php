@php
    use Falcon\Analytics\Enums\EventType;
    use Illuminate\Support\Str;

    $routeName = config('analytics.dashboard.route_name', 'analytics');
    $value = fn ($raw) => filled($raw) ? $raw : null;
    $subjectResolver = app(\Falcon\Analytics\Services\SubjectResolver::class);

    $formatSeconds = function (int $seconds): string {
        $minutes = intdiv($seconds, 60);

        return $minutes > 0
            ? trim($minutes.' min '.($seconds % 60 > 0 ? ($seconds % 60).' s' : ''))
            : $seconds.' s';
    };

    $eventLabel = fn ($event) => $value($event->target_text) ?? $value($event->name) ?? __('Évènement');

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

    $utm = collect([
        __('Campagne') => $session->utm_campaign,
        __('Source UTM') => $session->utm_source,
        __('Support') => $session->utm_medium,
        __('Contenu') => $session->utm_content,
        __('Terme') => $session->utm_term,
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

    <div>
        <a href="{{ route($routeName.'.sessions') }}" class="inline-flex cursor-pointer items-center gap-x-1 text-[12px] font-medium text-secondary transition-colors hover:text-primary">
            <x-ui.icon name="arrow-left" class="h-3.5 w-3.5" />
            {{ __('Retour aux sessions') }}
        </a>
    </div>

    {{-- Header: identity + key figures --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-elevated">
                <x-ui.icon :name="$session->subject_type ? 'user' : 'user-circle'" class="h-6 w-6 text-secondary" />
            </span>
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-lg font-semibold text-primary">
                        {{ $session->subject_type ? $subjectResolver->display($session->subject_type, (int) $session->subject_id) : __('Visiteur anonyme') }}
                    </h1>
                    <x-ui.badge :color="$session->subject_type ? 'blue' : 'gray'">
                        {{ $session->subject_type ? $subjectResolver->label($session->subject_type) : __('Anonyme') }}
                    </x-ui.badge>
                </div>
                <p class="mt-0.5 text-[12px] text-muted">
                    {{ __('Session du :date', ['date' => $session->started_at->translatedFormat('d F Y à H:i')]) }}
                    · {{ $isReturning ? __('Visiteur récurrent') : __('Nouveau visiteur') }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-6 sm:gap-8">
            <div>
                <p class="text-[11px] text-muted">{{ __('Durée') }}</p>
                <p class="text-lg font-semibold tracking-tight text-primary">{{ $duration }}</p>
            </div>
            <div>
                <p class="text-[11px] text-muted">{{ __('Pages vues') }}</p>
                <p class="text-lg font-semibold tracking-tight text-primary">{{ $session->pageview_count }}</p>
            </div>
            <div>
                <p class="text-[11px] text-muted">{{ __('Clics') }}</p>
                <p class="text-lg font-semibold tracking-tight text-primary">{{ $clicksCount }}</p>
            </div>
            <div>
                <p class="text-[11px] text-muted">{{ __('Temps moy./page') }}</p>
                <p class="text-lg font-semibold tracking-tight text-primary">{{ $formatSeconds($avgPageSeconds) }}</p>
            </div>
        </div>
    </div>

    {{-- Body: journey (main) + details (sidebar) --}}
    <div class="grid gap-6 lg:grid-cols-3">

        <div class="lg:col-span-2">
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
                                            <span class="truncate">@if ($isPageview)<x-analytics::page-url :route="$event->route" />@else{{ $eventLabel($event) }}@endif</span>
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
                                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-elevated">
                                                <div class="h-full rounded-full bg-[#1684ea]/70" style="width: {{ $barPct }}%"></div>
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
                                                    <span @class(['min-w-0 truncate text-[12px]', 'font-medium text-emerald-600 dark:text-emerald-400' => $isConversion, 'text-secondary' => ! $isConversion])>{{ $eventLabel($child) }}</span>
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

        <div class="space-y-5">

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
                                <div class="flex items-center justify-between gap-2">
                                    <span class="flex min-w-0 items-center gap-2 text-[12px] text-secondary">
                                        <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $palette[$loop->index] ?? '#d1d5db' }}"></span>
                                        <span class="truncate">{{ $pageLabel }}</span>
                                    </span>
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
                <x-ui.section-header :title="__('Appareil')" class="mb-3">
                    <x-ui.icon :name="$deviceIcon" class="h-4 w-4 text-muted" />
                </x-ui.section-header>
                <dl class="space-y-2.5">
                    <x-analytics::detail-row :label="__('Type')" :value="$value($session->device_type) ? Str::title($session->device_type) : null" />
                    <x-analytics::detail-row :label="__('Navigateur')" :value="trim(($session->browser ?? '').' '.($session->browser_version ?? '')) ?: null" />
                    <x-analytics::detail-row :label="__('Système')" :value="trim(($session->os ?? '').' '.($session->os_version ?? '')) ?: null" />
                </dl>
            </x-ui.card>

            <x-ui.card>
                <x-ui.section-header :title="__('Localité')" class="mb-3" />
                <dl class="space-y-2.5">
                    <x-analytics::detail-row :label="__('Pays')">@if ($session->country)<x-analytics::country :code="$session->country" />@endif</x-analytics::detail-row>
                    <x-analytics::detail-row :label="__('Ville')" :value="$value($session->city)" />
                    <x-analytics::detail-row :label="__('IP')" :value="$value($session->ip)" mono />
                </dl>
            </x-ui.card>

            <x-ui.card>
                <x-ui.section-header :title="__('Acquisition')" class="mb-3" />
                <dl class="space-y-2.5">
                    <x-analytics::detail-row :label="__('Source')">@if ($session->source)<x-analytics::source :value="$session->source" />@endif</x-analytics::detail-row>
                    <x-analytics::detail-row :label="__('Page d\'entrée')">@if ($session->landing_route)<x-analytics::page-url :route="$session->landing_route" />@endif</x-analytics::detail-row>
                    <x-analytics::detail-row :label="__('Référent')" :value="$value($session->referrer)" />
                    @foreach ($utm as $utmLabel => $utmValue)
                        <x-analytics::detail-row :label="$utmLabel" :value="$utmValue" />
                    @endforeach
                </dl>
            </x-ui.card>

        </div>
    </div>

</div>
