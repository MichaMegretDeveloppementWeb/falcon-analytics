@php
    use Falcon\Analytics\Enums\EventType;

    $routeName = config('analytics.dashboard.route_name', 'analytics');
    $subjectResolver = app(\Falcon\Analytics\Services\SubjectResolver::class);

    $feedIcon = fn ($event): string => match (true) {
        in_array($event->name, $conversionNames, true) => 'check-circle',
        $event->type === EventType::Pageview => 'document-text',
        $event->type === EventType::Click => 'cursor-arrow-rays',
        default => 'bolt',
    };

    $maxPages = max(array_column($topPages, 'total') ?: [0]);
    $peakMinute = max($minuteSeries ?: [0]);
@endphp

<div class="space-y-6" wire:poll.{{ $pollSeconds }}s.visible>

    @include('analytics::livewire.dashboard.partials.tooltip-host')

    <x-ui.page-header
        :title="__('Temps réel')"
        :description="__('Activité des :count dernières minutes, actualisée toutes les :seconds secondes', ['count' => $windowMinutes, 'seconds' => $pollSeconds])" />

    {{-- KPI : maintenant + fenêtre récente --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-ui.stat-card :label="__('En ligne maintenant')" :value="number_format($onlineCount, 0, ',', ' ')" icon="signal" />
        <x-ui.stat-card :label="__('Visiteurs (:count min)', ['count' => $windowMinutes])" :value="number_format($window['visitors'], 0, ',', ' ')" icon="users" />
        <x-ui.stat-card :label="__('Pages vues (:count min)', ['count' => $windowMinutes])" :value="number_format($window['pageviews'], 0, ',', ' ')" icon="document-text" />
        <x-ui.stat-card :label="__('Conversions (:count min)', ['count' => $windowMinutes])" :value="number_format($conversionsCount, 0, ',', ' ')" icon="check-circle" />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <div class="space-y-6 lg:col-span-2">

            {{-- Pages vues par minute (mise à jour en place à chaque tick) --}}
            <x-ui.card>
                <div class="mb-3 flex items-baseline justify-between gap-3">
                    <x-ui.section-header :title="__('Activité par minute')" :description="__('Pages vues')" />
                    <span class="text-[11px] text-muted">{{ __('pic :count/min', ['count' => number_format($peakMinute, 0, ',', ' ')]) }}</span>
                </div>
                <x-analytics::live-line :labels="array_keys($minuteSeries)" :values="array_values($minuteSeries)" channel="pulse" height="h-52" />
            </x-ui.card>

            {{-- Répartitions : donuts jumeaux, miroir de la section Audience --}}
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <x-ui.card>
                    <x-ui.section-header :title="__('Appareils')" class="mb-4" />
                    @if ($devices['total'] > 0)
                        <div class="flex items-center gap-5">
                            <x-analytics::live-donut
                                :labels="$devices['labels']"
                                :values="$devices['values']"
                                :colors="$devices['colors']"
                                :total="number_format($devices['total'], 0, ',', ' ')"
                                :caption="__('sessions')"
                                channel="devices" />
                            <div class="flex-1 space-y-2.5">
                                @foreach ($devices['labels'] as $index => $label)
                                    <div class="flex items-center justify-between gap-2" wire:key="rt-device-{{ $label }}">
                                        <span class="flex items-center gap-2 text-[13px] text-secondary"><span class="h-2 w-2 rounded-full" style="background:{{ $devices['colors'][$index] }}"></span>{{ $label }}</span>
                                        <span class="text-[13px]"><span class="font-semibold text-primary">{{ ((int) round($devices['values'][$index] / $devices['total'] * 100))."\u{00A0}%" }}</span> <span class="text-muted">{{ number_format($devices['values'][$index], 0, ',', ' ') }}</span></span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <x-ui.empty-state icon="device-phone-mobile" :title="__('Aucune session')" :description="__('Aucune session sur la fenêtre.')" />
                    @endif
                </x-ui.card>

                <x-ui.card>
                    <x-ui.section-header :title="__('Sources')" class="mb-4" />
                    @if ($sources['total'] > 0)
                        <div class="flex items-center gap-5">
                            <x-analytics::live-donut
                                :labels="$sources['labels']"
                                :values="$sources['values']"
                                :colors="$sources['colors']"
                                :total="number_format($sources['total'], 0, ',', ' ')"
                                :caption="__('sessions')"
                                channel="sources" />
                            <div class="flex-1 space-y-2.5">
                                @foreach ($sources['labels'] as $index => $label)
                                    <div class="flex items-center justify-between gap-2" wire:key="rt-source-{{ $label }}">
                                        <span class="flex items-center gap-2 text-[13px] text-secondary"><span class="h-2 w-2 rounded-full" style="background:{{ $sources['colors'][$index] }}"></span>{{ $label }}</span>
                                        <span class="text-[13px]"><span class="font-semibold text-primary">{{ ((int) round($sources['values'][$index] / $sources['total'] * 100))."\u{00A0}%" }}</span> <span class="text-muted">{{ number_format($sources['values'][$index], 0, ',', ' ') }}</span></span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <x-ui.empty-state icon="signal" :title="__('Aucune session')" :description="__('Aucune session sur la fenêtre.')" />
                    @endif
                </x-ui.card>
            </div>

            {{-- Pages du moment --}}
            <x-ui.card>
                <x-ui.section-header :title="__('Pages du moment')" class="mb-4" />
                @forelse ($topPages as $item)
                    @php $pct = $maxPages > 0 ? round($item['total'] / $maxPages * 100) : 0; @endphp
                    <div class="flex items-center gap-3 py-1.5" wire:key="rt-page-{{ md5($item['url']) }}">
                        <span class="min-w-0 w-56 shrink-0 truncate text-[13px] text-primary"><x-analytics::page-url :url="$item['url']" /></span>
                        <div class="relative h-1.5 flex-1 overflow-hidden rounded-full bg-elevated">
                            <div class="absolute inset-y-0 left-0 rounded-full bg-[#1684ea]/70" style="width: {{ $pct }}%"></div>
                        </div>
                        <span class="w-10 shrink-0 text-right text-[12px] font-medium text-secondary">{{ number_format($item['total'], 0, ',', ' ') }}</span>
                    </div>
                @empty
                    <p class="py-6 text-center text-[13px] text-secondary">{{ __('Aucune page vue sur la fenêtre.') }}</p>
                @endforelse
            </x-ui.card>
        </div>

        {{-- Flux d'activité --}}
        <x-ui.card class="lg:col-span-1">
            <x-ui.section-header :title="__('Flux d\'activité')" :description="__('Les :count derniers événements', ['count' => count($feed)])" class="mb-4" />

            @if ($feed->isEmpty())
                <p class="py-10 text-center text-[13px] text-secondary">{{ __('Aucune activité sur les :count dernières minutes.', ['count' => $windowMinutes]) }}</p>
            @else
                {{-- Hauteur bornée : le flux défile à l'intérieur de sa carte. --}}
                <ul class="max-h-[44rem] divide-y divide-[color:var(--color-gray-100)] overflow-y-auto pr-1 dark:divide-gray-800">
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
                               class="flex cursor-pointer items-start gap-3 py-2.5 hover:bg-elevated/50">
                                <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {{ $isConversion ? 'bg-emerald-50 dark:bg-emerald-500/10' : 'bg-elevated' }}">
                                    <x-ui.icon :name="$feedIcon($event)" class="h-3.5 w-3.5 {{ $isConversion ? 'text-emerald-600 dark:text-emerald-400' : 'text-secondary' }}" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-[13px] font-medium text-primary">{{ $who }}</span>
                                    <span class="block truncate text-[12px] {{ $isConversion ? 'font-medium text-emerald-600 dark:text-emerald-400' : 'text-secondary' }}">
                                        {{ $action }}@if ($event->type === EventType::Pageview && filled($event->url)) · <x-analytics::page-url :url="$event->url" />@endif
                                    </span>
                                </span>
                                <span class="shrink-0 whitespace-nowrap text-[11px] text-muted">{{ $event->occurred_at->diffForHumans(['short' => true]) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>
</div>
