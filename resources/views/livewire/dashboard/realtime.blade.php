@php
    use Falcon\Analytics\Enums\EventType;
    use Falcon\Analytics\Support\DeviceLabel;

    $routeName = config('analytics.dashboard.route_name', 'analytics');
    $subjectResolver = app(\Falcon\Analytics\Services\SubjectResolver::class);

    // Palette de reference (Wix) : encre #000624, accent #116DFF, en ligne #54CE91.
    $ink = 'text-[#000624] dark:text-gray-100';
    $inkMuted = 'text-[#000624]/40 dark:text-gray-500';
    $inkSoft = 'text-[#44485F] dark:text-gray-400';

    $deviceIcon = fn (?string $type): string => match (strtolower((string) $type)) {
        'mobile' => 'device-phone-mobile',
        'tablet' => 'device-tablet',
        default => 'computer-desktop',
    };

    $feedIcon = fn ($event): string => match (true) {
        in_array($event->name, $conversionNames, true) => 'check-circle',
        $event->type === EventType::Pageview => 'document-text',
        $event->type === EventType::Click => 'cursor-arrow-rays',
        default => 'bolt',
    };

    $onlineThreshold = now()->subSeconds(max(1, (int) config('analytics.realtime.online_seconds', 60)));
    $peakMinute = max($minuteSeries ?: [0]);
    $maxPages = max(array_column($topPages, 'total') ?: [0]);
    $countriesOnline = array_values(array_filter($countries, fn (array $row): bool => $row['online'] > 0));

    $listTime = fn ($moment) => $moment->isToday() ? $moment->format('H:i') : $moment->translatedFormat('j M, H:i');
@endphp

<div class="space-y-6" wire:poll.{{ $pollSeconds }}s.visible>

    @include('analytics::livewire.dashboard.partials.tooltip-host')

    <x-ui.page-header
        :title="__('Temps réel')"
        :description="__('Activité des :count dernières minutes, actualisée toutes les :seconds secondes', ['count' => $windowMinutes, 'seconds' => $pollSeconds])" />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <div class="space-y-6 lg:col-span-2">

            {{-- Carte principale façon Wix : onglets-KPI, carte + pays, répartitions --}}
            <div class="rounded-xl border border-base bg-surface" x-data="{ tab: 'window' }">

                {{-- Onglets --}}
                <div class="flex items-stretch px-5 pt-1">
                    <button type="button"
                            @click="tab = 'window'; $dispatch('analytics-realtime-mode', { mode: 'window' })"
                            class="flex-1 cursor-pointer border-b-[3px] pb-3 pr-4 pt-3 text-left transition-colors sm:pr-6"
                            :class="tab === 'window' ? 'border-[#116DFF]' : 'border-transparent'">
                        <span class="block text-[14px] font-medium {{ $ink }}">{{ __('Visiteurs (:count dernières minutes)', ['count' => $windowMinutes]) }}</span>
                        <span class="mt-0.5 block text-[21px] font-bold leading-6 {{ $ink }}">{{ number_format($window['visitors'], 0, ',', ' ') }}</span>
                    </button>
                    <button type="button"
                            @click="tab = 'online'; $dispatch('analytics-realtime-mode', { mode: 'online' })"
                            class="flex-1 cursor-pointer border-b-[3px] pb-3 pt-3 text-left transition-colors"
                            :class="tab === 'online' ? 'border-[#116DFF]' : 'border-transparent'">
                        <span class="block text-[14px] font-medium {{ $ink }}">{{ __('Visiteurs en ligne') }}</span>
                        <span class="mt-0.5 flex items-center gap-x-2 text-[21px] font-bold leading-6 {{ $ink }}">
                            {{ number_format($onlineCount, 0, ',', ' ') }}
                            <span class="h-2.5 w-2.5 rounded-full bg-[#54CE91] ring-4 ring-[#54CE91]/20"></span>
                        </span>
                    </button>
                </div>

                <div class="border-t border-subtle"></div>

                {{-- Carte + Pays --}}
                <div class="grid grid-cols-1 md:grid-cols-5">
                    <div class="p-5 md:col-span-3">
                        <x-analytics::world-map :points="$map['points']" channel="map" />
                        @if ($map['unlocated'] > 0)
                            <p class="mt-2 text-right text-[11px] {{ $inkMuted }}">
                                {{ $map['unlocated'] === 1 ? __('dont 1 session non localisée') : __('dont :count sessions non localisées', ['count' => $map['unlocated']]) }}
                            </p>
                        @endif
                    </div>
                    <div class="border-t border-subtle p-5 md:col-span-2 md:border-l md:border-t-0">
                        <p class="mb-3 text-[14px] font-medium {{ $inkMuted }}">{{ __('Pays') }}</p>

                        <div x-show="tab === 'window'" class="space-y-2.5">
                            @forelse ($countries as $row)
                                <div class="flex items-center justify-between gap-2" wire:key="rt-country-{{ $row['country'] ?? 'xx' }}">
                                    <span class="min-w-0 truncate text-[14px] font-medium {{ $ink }}"><x-analytics::country :code="$row['country']" /></span>
                                    <span class="shrink-0 text-[14px] font-medium {{ $ink }}">{{ number_format($row['total'], 0, ',', ' ') }}</span>
                                </div>
                            @empty
                                <p class="py-4 text-[13px] {{ $inkSoft }}">{{ __('Aucun visiteur localisé sur la fenêtre.') }}</p>
                            @endforelse
                        </div>

                        <div x-show="tab === 'online'" x-cloak class="space-y-2.5">
                            @forelse ($countriesOnline as $row)
                                <div class="flex items-center justify-between gap-2" wire:key="rt-country-online-{{ $row['country'] ?? 'xx' }}">
                                    <span class="min-w-0 truncate text-[14px] font-medium {{ $ink }}"><x-analytics::country :code="$row['country']" /></span>
                                    <span class="shrink-0 text-[14px] font-medium {{ $ink }}">{{ number_format($row['online'], 0, ',', ' ') }}</span>
                                </div>
                            @empty
                                <p class="py-4 text-[13px] {{ $inkSoft }}">{{ __('Personne en ligne actuellement.') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="border-t border-subtle"></div>

                {{-- Source de trafic / Appareil : deux camemberts --}}
                <div class="grid grid-cols-1 md:grid-cols-2 md:divide-x md:divide-[color:var(--color-gray-100)] dark:md:divide-gray-800">
                    <div class="p-5">
                        <p class="mb-3 text-[14px] font-medium {{ $inkMuted }}">{{ __('Source de trafic') }}</p>
                        @if ($sources['total'] > 0)
                            <div class="flex items-center gap-5">
                                <x-analytics::live-donut
                                    :labels="$sources['labels']"
                                    :values="$sources['values']"
                                    :colors="$sources['colors']"
                                    :total="number_format($sources['count'], 0, ',', ' ')"
                                    :caption="$sources['count'] > 1 ? __('sources') : __('source')"
                                    size="h-24 w-24"
                                    channel="sources" />
                                <div class="flex-1 space-y-2.5">
                                    @foreach ($sources['labels'] as $index => $label)
                                        <div wire:key="rt-source-{{ $label }}">
                                            <span class="flex items-center gap-2 text-[13px] {{ $inkSoft }}"><span class="h-2 w-2 shrink-0 rounded-full" style="background:{{ $sources['colors'][$index] }}"></span>{{ $label }}</span>
                                            <span class="block pl-4 text-[13px]"><span class="font-bold {{ $ink }}">{{ ((int) round($sources['values'][$index] / $sources['total'] * 100)) }}%</span> <span class="{{ $inkMuted }}">· {{ number_format($sources['values'][$index], 0, ',', ' ') }}</span></span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <p class="py-4 text-[13px] {{ $inkSoft }}">{{ __('Aucune session.') }}</p>
                        @endif
                    </div>
                    <div class="border-t border-subtle p-5 md:border-t-0">
                        <p class="mb-3 text-[14px] font-medium {{ $inkMuted }}">{{ __('Appareil') }}</p>
                        @if ($devices['total'] > 0)
                            <div class="flex items-center gap-5">
                                <x-analytics::live-donut
                                    :labels="$devices['labels']"
                                    :values="$devices['values']"
                                    :colors="$devices['colors']"
                                    :total="number_format($devices['count'], 0, ',', ' ')"
                                    :caption="$devices['count'] > 1 ? __('types') : __('type')"
                                    size="h-24 w-24"
                                    channel="devices" />
                                <div class="flex-1 space-y-2.5">
                                    @foreach ($devices['labels'] as $index => $label)
                                        <div wire:key="rt-device-{{ $label }}">
                                            <span class="flex items-center gap-2 text-[13px] {{ $inkSoft }}"><span class="h-2 w-2 shrink-0 rounded-full" style="background:{{ $devices['colors'][$index] }}"></span>{{ $label }}</span>
                                            <span class="block pl-4 text-[13px]"><span class="font-bold {{ $ink }}">{{ ((int) round($devices['values'][$index] / $devices['total'] * 100)) }}%</span> <span class="{{ $inkMuted }}">· {{ number_format($devices['values'][$index], 0, ',', ' ') }}</span></span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <p class="py-4 text-[13px] {{ $inkSoft }}">{{ __('Aucune session.') }}</p>
                        @endif
                    </div>
                </div>

                <div class="border-t border-subtle"></div>

                {{-- Pages vues : section dédiée pleine largeur, barres de proportion --}}
                <div class="p-5">
                    <p class="mb-3 text-[14px] font-medium {{ $inkMuted }}">{{ __('Pages vues') }}</p>
                    @forelse ($topPages as $item)
                        @php $pct = $maxPages > 0 ? round($item['total'] / $maxPages * 100) : 0; @endphp
                        {{-- Petit écran : URL + compte sur la ligne, barre pleine largeur dessous. --}}
                        <div class="py-1.5" wire:key="rt-page-{{ md5($item['url']) }}">
                            <div class="flex items-center gap-3">
                                <span class="min-w-0 flex-1 truncate text-[14px] {{ $ink }} sm:w-56 sm:flex-none"><x-analytics::page-url :url="$item['url']" /></span>
                                <div class="relative hidden h-1.5 flex-1 overflow-hidden rounded-full bg-elevated sm:block">
                                    <div class="absolute inset-y-0 left-0 rounded-full bg-[#116DFF]/70" style="width: {{ $pct }}%"></div>
                                </div>
                                <span class="w-10 shrink-0 text-right text-[13px] font-medium {{ $ink }}">{{ number_format($item['total'], 0, ',', ' ') }}</span>
                            </div>
                            <div class="relative mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-elevated sm:hidden">
                                <div class="absolute inset-y-0 left-0 rounded-full bg-[#116DFF]/70" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="py-4 text-[13px] {{ $inkSoft }}">{{ __('Aucune page vue sur la fenêtre.') }}</p>
                    @endforelse
                </div>
            </div>

            {{-- Activité par minute (sous la carte) --}}
            <x-ui.card>
                <div class="mb-3 flex flex-wrap items-baseline justify-between gap-3">
                    <p class="text-[16px] font-bold {{ $ink }}">{{ __('Activité par minute') }}</p>
                    <div class="flex items-baseline gap-x-6">
                        <span class="text-[12px] {{ $inkMuted }}">{{ __('Pages vues') }} <span class="text-[14px] font-bold {{ $ink }}">{{ number_format($window['pageviews'], 0, ',', ' ') }}</span></span>
                        <span class="text-[12px] {{ $inkMuted }}">{{ __('Conversions') }} <span class="text-[14px] font-bold {{ $ink }}">{{ number_format($conversionsCount, 0, ',', ' ') }}</span></span>
                        <span class="text-[11px] {{ $inkMuted }}">{{ __('pic :count/min', ['count' => number_format($peakMinute, 0, ',', ' ')]) }}</span>
                    </div>
                </div>
                <x-analytics::live-line :labels="array_keys($minuteSeries)" :values="array_values($minuteSeries)" channel="pulse" color="#116DFF" height="h-48" />
            </x-ui.card>
        </div>

        <div class="space-y-6 lg:col-span-1">

            {{-- Visiteurs récents (24 dernières heures) --}}
            <div class="rounded-xl border border-base bg-surface">
                <div class="px-5 py-4">
                    <p class="text-[16px] font-bold {{ $ink }}">{{ __('Visiteurs récents') }}</p>
                    <p class="mt-0.5 text-[12px] {{ $inkMuted }}">{{ __('24 dernières heures') }}</p>
                </div>
                <div class="border-t border-subtle"></div>

                @if ($recentSessions->isEmpty())
                    <p class="px-5 py-10 text-center text-[13px] {{ $inkSoft }}">{{ __('Aucun visiteur sur les 24 dernières heures.') }}</p>
                @else
                    <ul class="max-h-[24rem] divide-y divide-[color:var(--color-gray-100)] overflow-y-auto dark:divide-gray-800">
                        @foreach ($recentSessions as $session)
                            @php
                                $attribution = $attributions[$session->id] ?? null;
                                $who = $attribution !== null
                                    ? ($subjectNames[$attribution->guard.':'.$attribution->id] ?? $subjectResolver->label($attribution->guard).' #'.$attribution->id)
                                    : __('Visiteur #:id', ['id' => $session->visitor_id]);
                                $isOnline = $session->last_activity_at->greaterThanOrEqualTo($onlineThreshold);
                            @endphp
                            <li wire:key="rt-session-{{ $session->id }}">
                                <a href="{{ route($routeName.'.sessions.show', $session) }}" class="flex cursor-pointer items-center gap-3 px-5 py-3 transition-colors hover:bg-elevated/50">
                                    <x-ui.icon :name="$deviceIcon($session->device_type)" class="h-5 w-5 shrink-0 {{ $inkSoft }}" />
                                    <span class="min-w-0 flex-1">
                                        <span class="flex items-center gap-x-1.5">
                                            <span class="truncate text-[14px] font-medium {{ $ink }}">{{ $who }}</span>
                                            @if ($isOnline)
                                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-[#54CE91]"></span>
                                            @endif
                                        </span>
                                        <span class="block truncate text-[12px] {{ $inkMuted }}">
                                            {{ $listTime($session->last_activity_at) }}@if ($session->city) · {{ $session->city }}@endif
                                        </span>
                                    </span>
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-[#116DFF]/30 text-[#116DFF]">
                                        <x-ui.icon name="chevron-right" class="h-4 w-4" />
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Activité en direct (24 dernières heures) : le type d'événement en vedette --}}
            <div class="rounded-xl border border-base bg-surface">
                <div class="px-5 py-4">
                    <p class="text-[16px] font-bold {{ $ink }}">{{ __('Activité en direct') }}</p>
                    <p class="mt-0.5 text-[12px] {{ $inkMuted }}">{{ __(':count dernières minutes', ['count' => $windowMinutes]) }}</p>
                </div>
                <div class="border-t border-subtle"></div>

                @if ($feed->isEmpty())
                    <p class="px-5 py-10 text-center text-[13px] {{ $inkSoft }}">{{ __('Aucune activité sur les :count dernières minutes.', ['count' => $windowMinutes]) }}</p>
                @else
                    <ul class="max-h-[24rem] divide-y divide-[color:var(--color-gray-100)] overflow-y-auto dark:divide-gray-800">
                        @foreach ($feed as $event)
                            @php
                                $attribution = $event->session !== null ? ($attributions[$event->session->id] ?? null) : null;
                                $who = $attribution !== null
                                    ? ($subjectNames[$attribution->guard.':'.$attribution->id] ?? $subjectResolver->label($attribution->guard).' #'.$attribution->id)
                                    : __('Visiteur #:id', ['id' => $event->visitor_id]);
                                $isConversion = in_array($event->name, $conversionNames, true);
                                $action = match (true) {
                                    $event->type === EventType::Pageview => __('Page vue'),
                                    filled($event->name) => $eventLabels[$event->name] ?? $event->name,
                                    filled($event->target_text) => $event->target_text,
                                    default => __('Clic'),
                                };
                            @endphp
                            <li wire:key="rt-feed-{{ $event->id }}">
                                <a href="{{ $event->session_id !== null ? route($routeName.'.sessions.show', $event->session_id) : '#' }}"
                                   class="flex cursor-pointer items-start gap-3 px-5 py-3 transition-colors hover:bg-elevated/50">
                                    <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {{ $isConversion ? 'bg-[#54CE91]/15' : 'bg-elevated' }}">
                                        <x-ui.icon :name="$feedIcon($event)" class="h-3.5 w-3.5 {{ $isConversion ? 'text-[#22A96F]' : $inkSoft }}" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-[14px] font-medium {{ $isConversion ? 'text-[#22A96F]' : $ink }}">
                                            {{ $action }}@if ($event->type === EventType::Pageview && filled($event->url)) · <x-analytics::page-url :url="$event->url" />@endif
                                        </span>
                                        <span class="block truncate text-[12px] {{ $inkMuted }}">{{ $who }} · {{ $listTime($event->occurred_at) }}</span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
