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

    $duration = $formatSeconds((int) $session->started_at->diffInSeconds($session->last_activity_at));

    $deviceIcon = match (strtolower((string) $session->device_type)) {
        'mobile' => 'device-phone-mobile',
        'tablet' => 'device-tablet',
        'desktop' => 'computer-desktop',
        default => 'question-mark-circle',
    };

    $sessionCount = (int) ($session->visitor?->session_count ?? 1);
    $isReturning = $sessionCount > 1;

    $utm = collect([
        __('Campagne') => $session->utm_campaign,
        __('Source UTM') => $session->utm_source,
        __('Support') => $session->utm_medium,
        __('Contenu') => $session->utm_content,
        __('Terme') => $session->utm_term,
    ])->filter(fn ($v) => filled($v));
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
                            @endphp
                            <li class="relative flex gap-4 pb-6 last:pb-0">
                                @unless ($loop->last)
                                    <span class="absolute bottom-0 left-4 top-8 w-px bg-base"></span>
                                @endunless
                                <span class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-elevated ring-4 ring-surface">
                                    <x-ui.icon :name="$isPageview ? 'document-text' : 'bolt'" class="h-4 w-4 text-secondary" />
                                </span>
                                <div class="min-w-0 flex-1 pt-1">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <p class="min-w-0 truncate text-[13px] font-medium text-primary">
                                            @if ($isPageview)<x-analytics::page-url :route="$event->route" />@else{{ $eventLabel($event) }}@endif
                                        </p>
                                        <span class="shrink-0 text-[11px] tabular-nums text-muted">{{ $event->occurred_at->translatedFormat('H:i:s') }}</span>
                                    </div>
                                    @if ($isPageview && $step['seconds'] > 0)
                                        <p class="mt-0.5 text-[11px] text-muted">{{ $formatSeconds($step['seconds']) }} {{ __('sur la page') }}</p>
                                    @endif
                                    @if (! empty($step['children']))
                                        <div class="mt-2.5 space-y-1.5">
                                            @foreach ($step['children'] as $child)
                                                <div class="flex items-center gap-2">
                                                    <x-ui.icon name="cursor-arrow-rays" class="h-3.5 w-3.5 shrink-0 text-muted" />
                                                    <span class="min-w-0 truncate text-[12px] text-secondary">{{ $eventLabel($child) }}</span>
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
                <x-ui.section-header :title="__('Visiteur')" class="mb-3" />
                <dl class="space-y-2.5">
                    <x-analytics::detail-row :label="__('Identifiant')" :value="$session->visitor?->uuid" mono />
                    <x-analytics::detail-row :label="__('Sessions totales')" :value="(string) $sessionCount" />
                    <x-analytics::detail-row :label="__('Première visite')" :value="$session->visitor?->first_seen_at?->translatedFormat('d M Y, H:i')" />
                    <x-analytics::detail-row :label="__('Dernière visite')" :value="$session->visitor?->last_seen_at?->translatedFormat('d M Y, H:i')" />
                </dl>
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
