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

    $deviceLabel = $value($session->device_type) ? Str::title($session->device_type) : __('Inconnu');
    $deviceIcon = match (strtolower((string) $session->device_type)) {
        'mobile' => 'device-phone-mobile',
        'tablet' => 'device-tablet',
        'desktop' => 'computer-desktop',
        default => 'question-mark-circle',
    };

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

    {{-- Summary --}}
    <x-ui.card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-elevated">
                    <x-ui.icon :name="$session->subject_type ? 'user' : 'user-circle'" class="h-5 w-5 text-secondary" />
                </span>
                <div class="min-w-0">
                    <p class="text-[15px] font-semibold text-primary">
                        {{ $session->subject_type ? Str::headline($session->subject_type).' #'.$session->subject_id : __('Visiteur anonyme') }}
                    </p>
                    <p class="text-[12px] text-muted">
                        {{ __('Session du :date', ['date' => $session->started_at->translatedFormat('d F Y à H:i')]) }}
                        @if ($session->visitor?->uuid)
                            · <span class="font-mono">{{ Str::limit($session->visitor->uuid, 12, '') }}</span>
                        @endif
                    </p>
                </div>
            </div>
            <x-ui.badge :color="$session->subject_type ? 'blue' : 'gray'">
                {{ $session->subject_type ? __('Identifié') : __('Anonyme') }}
            </x-ui.badge>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-4 border-t border-subtle pt-5 sm:grid-cols-3 lg:grid-cols-5">
            <div class="flex items-center gap-2.5">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-elevated"><x-ui.icon name="clock" class="h-4 w-4 text-secondary" /></span>
                <div class="min-w-0"><p class="text-[11px] text-muted">{{ __('Durée') }}</p><p class="truncate text-[13px] font-medium text-primary">{{ $duration }}</p></div>
            </div>
            <div class="flex items-center gap-2.5">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-elevated"><x-ui.icon name="document-text" class="h-4 w-4 text-secondary" /></span>
                <div class="min-w-0"><p class="text-[11px] text-muted">{{ __('Pages vues') }}</p><p class="truncate text-[13px] font-medium text-primary">{{ $session->pageview_count }}</p></div>
            </div>
            <div class="flex items-center gap-2.5">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-elevated"><x-ui.icon :name="$deviceIcon" class="h-4 w-4 text-secondary" /></span>
                <div class="min-w-0"><p class="text-[11px] text-muted">{{ __('Appareil') }}</p><p class="truncate text-[13px] font-medium text-primary">{{ $deviceLabel }}@if ($session->browser) · {{ $session->browser }}@endif</p></div>
            </div>
            <div class="flex items-center gap-2.5">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-elevated"><x-ui.icon name="map-pin" class="h-4 w-4 text-secondary" /></span>
                <div class="min-w-0"><p class="text-[11px] text-muted">{{ __('Localité') }}</p><p class="truncate text-[13px] font-medium text-primary">@if ($session->country)<x-analytics::country :code="$session->country" :city="$session->city" />@else{{ __('Inconnu') }}@endif</p></div>
            </div>
            <div class="flex items-center gap-2.5">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-elevated"><x-ui.icon name="signal" class="h-4 w-4 text-secondary" /></span>
                <div class="min-w-0"><p class="text-[11px] text-muted">{{ __('Source') }}</p><p class="truncate text-[13px] font-medium text-primary">@if ($session->source)<x-analytics::source :value="$session->source" />@else{{ __('Directe') }}@endif</p></div>
            </div>
        </div>
    </x-ui.card>

    <x-ui.tab-group default="infos">
        <x-slot:tabs>
            <x-ui.tab name="infos" :label="__('Informations')" />
            <x-ui.tab name="events" :label="__('Parcours').' ('.$events->count().')'" />
        </x-slot:tabs>

        {{-- Informations --}}
        <x-ui.tab name="infos">
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">

                <x-ui.card class="border border-base">
                    <x-ui.section-header :title="__('Visiteur')" class="mb-3" />
                    <dl class="space-y-2.5">
                        <x-analytics::detail-row :label="__('Identifiant')" :value="$session->visitor?->uuid" mono />
                        <x-analytics::detail-row :label="__('Sujet')" :value="$session->subject_type ? Str::headline($session->subject_type).' #'.$session->subject_id : null" />
                        <x-analytics::detail-row :label="__('Sessions totales')" :value="(string) ($session->visitor?->session_count ?? 1)" />
                        <x-analytics::detail-row :label="__('Première visite')" :value="$session->visitor?->first_seen_at?->translatedFormat('d M Y, H:i')" />
                    </dl>
                </x-ui.card>

                <x-ui.card class="border border-base">
                    <x-ui.section-header :title="__('Appareil')" class="mb-3" />
                    <dl class="space-y-2.5">
                        <x-analytics::detail-row :label="__('Type')" :value="$value($session->device_type) ? Str::title($session->device_type) : null" />
                        <x-analytics::detail-row :label="__('Navigateur')" :value="trim(($session->browser ?? '').' '.($session->browser_version ?? '')) ?: null" />
                        <x-analytics::detail-row :label="__('Système')" :value="trim(($session->os ?? '').' '.($session->os_version ?? '')) ?: null" />
                        <x-analytics::detail-row :label="__('Robot')" :value="$session->is_bot ? __('Oui') : __('Non')" />
                    </dl>
                </x-ui.card>

                <x-ui.card class="border border-base">
                    <x-ui.section-header :title="__('Localité')" class="mb-3" />
                    <dl class="space-y-2.5">
                        <x-analytics::detail-row :label="__('Pays')">@if ($session->country)<x-analytics::country :code="$session->country" />@endif</x-analytics::detail-row>
                        <x-analytics::detail-row :label="__('Région')" :value="$value($session->region)" />
                        <x-analytics::detail-row :label="__('Ville')" :value="$value($session->city)" />
                        <x-analytics::detail-row :label="__('IP')" :value="$value($session->ip)" mono />
                    </dl>
                </x-ui.card>

                <x-ui.card class="border border-base">
                    <x-ui.section-header :title="__('Acquisition')" class="mb-3" />
                    <dl class="space-y-2.5">
                        <x-analytics::detail-row :label="__('Source')">@if ($session->source)<x-analytics::source :value="$session->source" />@endif</x-analytics::detail-row>
                        <x-analytics::detail-row :label="__('Référent')" :value="$value($session->referrer)" />
                        <x-analytics::detail-row :label="__('Campagne UTM')" :value="$value($session->utm_campaign)" />
                        <x-analytics::detail-row :label="__('Source UTM')" :value="$value($session->utm_source)" />
                    </dl>
                </x-ui.card>

                <x-ui.card class="border border-base">
                    <x-ui.section-header :title="__('Page d\'entrée')" class="mb-3" />
                    <dl class="space-y-2.5">
                        <x-analytics::detail-row :label="__('Page')">@if ($session->landing_route)<x-analytics::page-url :route="$session->landing_route" />@endif</x-analytics::detail-row>
                        <x-analytics::detail-row :label="__('URL complète')" :value="$value($session->landing_url)" mono />
                    </dl>
                </x-ui.card>

            </div>
        </x-ui.tab>

        {{-- Parcours --}}
        <x-ui.tab name="events">
            @if ($events->isEmpty())
                <x-ui.empty-state icon="signal" :title="__('Aucun évènement')" :description="__('Cette session n\'a enregistré aucun évènement.')" />
            @else
                <div class="pt-1">
                    <x-ui.timeline>
                        @foreach ($events as $event)
                            @php
                                $meta = $eventMeta($event->type);
                                $human = $value($event->target_text) ?? $value($event->name);
                                $itemTitle = match ($event->type) {
                                    EventType::Pageview => $meta['label'],
                                    EventType::Click => $meta['label'].($human ? ' · '.$human : ''),
                                    default => $human ?? $meta['label'],
                                };
                            @endphp
                            <x-ui.timeline.item
                                :icon="$meta['icon']"
                                :color="$meta['color']"
                                :title="$itemTitle"
                                :date="$event->occurred_at->translatedFormat('d M, H:i:s')">
                                @if ($event->route)
                                    @if ($event->type === EventType::Click)<span class="text-muted">{{ __('sur') }}</span> @endif<x-analytics::page-url :route="$event->route" />
                                @endif
                            </x-ui.timeline.item>
                        @endforeach
                    </x-ui.timeline>
                </div>
            @endif
        </x-ui.tab>
    </x-ui.tab-group>

</div>
