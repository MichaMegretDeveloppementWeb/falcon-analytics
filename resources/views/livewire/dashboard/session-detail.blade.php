@php
    use Falcon\Analytics\Enums\EventType;
    use Illuminate\Support\Str;

    $routeName = config('analytics.dashboard.route_name', 'analytics');

    $value = fn ($raw) => filled($raw) ? $raw : null;

    $seconds = (int) $session->started_at->diffInSeconds($session->last_activity_at);
    $minutes = intdiv($seconds, 60);
    $duration = $minutes > 0
        ? trim($minutes.' min '.($seconds % 60 > 0 ? ($seconds % 60).' s' : ''))
        : $seconds.' s';

    $eventMeta = fn (EventType $type): array => match ($type) {
        EventType::Pageview => ['icon' => 'document-text', 'color' => 'gray', 'label' => __('Page vue')],
        EventType::Click => ['icon' => 'cursor-arrow-rays', 'color' => 'indigo', 'label' => __('Clic')],
        EventType::Custom => ['icon' => 'bolt', 'color' => 'emerald', 'label' => __('Évènement')],
        default => ['icon' => 'signal', 'color' => 'gray', 'label' => __('Activité')],
    };
@endphp

<div class="space-y-6">

    <div>
        <a href="{{ route($routeName.'.sessions') }}" class="inline-flex cursor-pointer items-center gap-x-1 text-[12px] font-medium text-secondary transition-colors hover:text-primary">
            <x-ui.icon name="arrow-left" class="h-3.5 w-3.5" />
            {{ __('Retour aux sessions') }}
        </a>
    </div>

    <x-ui.page-header
        :title="$session->subject_type ? Str::headline($session->subject_type).' #'.$session->subject_id : __('Visiteur anonyme')"
        :description="__('Session du :date', ['date' => $session->started_at->translatedFormat('d F Y à H:i')])">
        <x-ui.badge :color="$session->subject_type ? 'indigo' : 'gray'">
            {{ $session->subject_type ? __('Identifié') : __('Anonyme') }}
        </x-ui.badge>
    </x-ui.page-header>

    <x-ui.tab-group default="infos">
        <x-slot:tabs>
            <x-ui.tab name="infos" :label="__('Informations')" />
            <x-ui.tab name="events" :label="__('Évènements').' ('.$events->count().')'" />
        </x-slot:tabs>

        {{-- Informations --}}
        <x-ui.tab name="infos">
            <div class="grid gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">

                <div class="space-y-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted">{{ __('Session') }}</p>
                    <x-analytics::detail-row :label="__('Début')" :value="$session->started_at->translatedFormat('d M Y, H:i:s')" />
                    <x-analytics::detail-row :label="__('Dernière activité')" :value="$session->last_activity_at->translatedFormat('d M Y, H:i:s')" />
                    <x-analytics::detail-row :label="__('Durée')" :value="$duration" />
                    <x-analytics::detail-row :label="__('Pages vues')" :value="(string) $session->pageview_count" />
                    <x-analytics::detail-row :label="__('Évènements')" :value="(string) $session->event_count" />
                </div>

                <div class="space-y-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted">{{ __('Visiteur') }}</p>
                    <x-analytics::detail-row :label="__('Identifiant')" :value="$session->visitor?->uuid" mono />
                    <x-analytics::detail-row :label="__('Sujet')" :value="$session->subject_type ? Str::headline($session->subject_type).' #'.$session->subject_id : null" />
                    <x-analytics::detail-row :label="__('Sessions totales')" :value="(string) ($session->visitor?->session_count ?? 1)" />
                    <x-analytics::detail-row :label="__('Vu pour la première fois')" :value="$session->visitor?->first_seen_at?->translatedFormat('d M Y, H:i')" />
                </div>

                <div class="space-y-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted">{{ __('Appareil') }}</p>
                    <x-analytics::detail-row :label="__('Type')" :value="$value($session->device_type) ? Str::title($session->device_type) : null" />
                    <x-analytics::detail-row :label="__('Navigateur')" :value="trim(($session->browser ?? '').' '.($session->browser_version ?? '')) ?: null" />
                    <x-analytics::detail-row :label="__('Système')" :value="trim(($session->os ?? '').' '.($session->os_version ?? '')) ?: null" />
                    <x-analytics::detail-row :label="__('Robot')" :value="$session->is_bot ? __('Oui') : __('Non')" />
                </div>

                <div class="space-y-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted">{{ __('Localité') }}</p>
                    <x-analytics::detail-row :label="__('Pays')" :value="$value($session->country)" />
                    <x-analytics::detail-row :label="__('Région')" :value="$value($session->region)" />
                    <x-analytics::detail-row :label="__('Ville')" :value="$value($session->city)" />
                    <x-analytics::detail-row :label="__('IP')" :value="$value($session->ip)" mono />
                </div>

                <div class="space-y-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted">{{ __('Acquisition') }}</p>
                    <x-analytics::detail-row :label="__('Source')" :value="$value($session->source) ? Str::headline($session->source) : null" />
                    <x-analytics::detail-row :label="__('Référent')" :value="$value($session->referrer)" />
                    <x-analytics::detail-row :label="__('Campagne UTM')" :value="$value($session->utm_campaign)" />
                    <x-analytics::detail-row :label="__('Source UTM')" :value="$value($session->utm_source)" />
                </div>

                <div class="space-y-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted">{{ __('Entrée') }}</p>
                    <x-analytics::detail-row :label="__('Route d\'entrée')" :value="$value($session->landing_route)" />
                    <x-analytics::detail-row :label="__('URL d\'entrée')" :value="$value($session->landing_url)" />
                </div>

            </div>
        </x-ui.tab>

        {{-- Évènements --}}
        <x-ui.tab name="events">
            @if ($events->isEmpty())
                <x-ui.empty-state icon="signal" :title="__('Aucun évènement')" :description="__('Cette session n\'a enregistré aucun évènement.')" />
            @else
                <x-ui.timeline>
                    @foreach ($events as $event)
                        @php
                            $meta = $eventMeta($event->type);
                            $title = match ($event->type) {
                                EventType::Pageview => $event->route ?? $event->url ?? __('Page vue'),
                                EventType::Click => $event->name ?? $event->target_text ?? __('Clic'),
                                default => $event->name ?? $meta['label'],
                            };
                        @endphp
                        <x-ui.timeline.item
                            :icon="$meta['icon']"
                            :color="$meta['color']"
                            :title="$meta['label'].' · '.e($title)"
                            :date="$event->occurred_at->translatedFormat('d M, H:i:s')">
                            @if ($event->type === EventType::Click && $event->route)
                                {{ __('sur :route', ['route' => $event->route]) }}
                            @elseif ($event->type === EventType::Pageview && $event->url)
                                {{ $event->url }}
                            @endif
                        </x-ui.timeline.item>
                    @endforeach
                </x-ui.timeline>
            @endif
        </x-ui.tab>
    </x-ui.tab-group>

</div>
