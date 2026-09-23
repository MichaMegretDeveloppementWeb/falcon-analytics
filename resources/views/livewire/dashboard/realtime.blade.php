@php
    use Falcon\Analytics\Support\NumberLabel;

    // The board's ink, in three weights. Tokens, with no dark variant beside
    // them: a token already carries both of its values.
    $ink = 'an:text-ink';
    $inkMuted = 'an:text-ink/40';
    $inkSoft = 'an:text-ink-soft';

    $peakMinute = max($minuteSeries ?: [0]);
    $maxPages = max(array_column($topPages, 'total') ?: [0]);
    $countriesOnline = array_values(array_filter($countries, fn (array $row): bool => $row['online'] > 0));
@endphp

{{-- The root wraps rather than replaces: the interval sits in the directive's
     NAME, and a component tag accepts no interpolation inside an attribute
     name. --}}
<x-analytics::root area="admin">
<div class="an:space-y-6" wire:poll.{{ $pollSeconds }}s.visible>

    @include('analytics::livewire.dashboard.partials.tooltip-host')

    <x-ui::page-header
        :title="__('Temps réel')"
        :description="trans_choice('{1} Activité de la dernière minute|[2,*] Activité des :count dernières minutes', $windowMinutes).', '.trans_choice('{1} actualisée chaque seconde|[2,*] actualisée toutes les :count secondes', $pollSeconds)" />

    <div class="an:grid an:grid-cols-1 an:gap-6 an:lg:grid-cols-3">

        <div class="an:space-y-6 an:lg:col-span-2">

            {{-- Main card --}}
            <div class="an:rounded-xl an:border an:border-default an:bg-surface" x-data="anRealtimeTabs">

                {{-- Tabs --}}
                <div class="an:flex an:items-stretch an:px-5 an:pt-1">
                    <button type="button"
                            @click="choose('window')"
                            class="an:flex-1 an:cursor-pointer an:border-b-[3px] an:pb-3 an:pr-4 an:pt-3 an:text-left an:transition-colors an:sm:pr-6"
                            {{-- `window` is a tab name, not a class; the other two are. --}}
                            :class="tab === 'window' ? 'an:border-accent' : 'an:border-transparent'">
                        <span class="an:block an:text-[14px] an:font-medium {{ $ink }}">{{ trans_choice('{1} Visiteurs (dernière minute)|[2,*] Visiteurs (:count dernières minutes)', $windowMinutes) }}</span>
                        <span class="an:mt-0.5 an:block an:text-[21px] an:font-bold an:leading-6 {{ $ink }}">{{ NumberLabel::for($window['visitors']) }}</span>
                    </button>
                    <button type="button"
                            @click="choose('online')"
                            class="an:flex-1 an:cursor-pointer an:border-b-[3px] an:pb-3 an:pt-3 an:text-left an:transition-colors"
                            :class="tab === 'online' ? 'an:border-accent' : 'an:border-transparent'">
                        <span class="an:block an:text-[14px] an:font-medium {{ $ink }}">{{ __('Visiteurs en ligne') }}</span>
                        <span class="an:mt-0.5 an:flex an:items-center an:gap-x-2 an:text-[21px] an:font-bold an:leading-6 {{ $ink }}">
                            {{ NumberLabel::for($onlineCount) }}
                            <span class="an:h-2.5 an:w-2.5 an:rounded-full an:bg-online an:ring-4 an:ring-online/20"></span>
                        </span>
                    </button>
                </div>

                <div class="an:border-t an:border-subtle"></div>

                {{-- Map + countries --}}
                <div class="an:grid an:grid-cols-1 an:md:grid-cols-5">
                    <div class="an:p-5 an:md:col-span-3">
                        <x-analytics::world-map :points="$map['points']" channel="map" />
                        @if ($map['unlocated'] > 0)
                            <p class="an:mt-2 an:text-right an:text-[11px] {{ $inkMuted }}">
                                {{ $map['unlocated'] === 1 ? __('dont 1 session non localisée') : __('dont :count sessions non localisées', ['count' => $map['unlocated']]) }}
                            </p>
                        @endif
                    </div>
                    <div class="an:border-t an:border-subtle an:p-5 an:md:col-span-2 an:md:border-l an:md:border-t-0">
                        <p class="an:mb-3 an:text-[14px] an:font-medium {{ $inkMuted }}">{{ __('Pays') }}</p>

                        <div x-show="tab === 'window'" class="an:space-y-2.5">
                            @forelse ($countries as $row)
                                <div class="an:flex an:items-center an:justify-between an:gap-2" wire:key="rt-country-{{ $row['country'] ?? 'xx' }}">
                                    <span class="an:min-w-0 an:truncate an:text-[14px] an:font-medium {{ $ink }}"><x-analytics::country :code="$row['country']" /></span>
                                    <span class="an:shrink-0 an:text-[14px] an:font-medium {{ $ink }}">{{ NumberLabel::for($row['total']) }}</span>
                                </div>
                            @empty
                                <p class="an:py-4 an:text-[13px] {{ $inkSoft }}">{{ __('Aucun visiteur localisé sur la fenêtre.') }}</p>
                            @endforelse
                        </div>

                        <div x-show="tab === 'online'" x-cloak class="an:space-y-2.5">
                            @forelse ($countriesOnline as $row)
                                <div class="an:flex an:items-center an:justify-between an:gap-2" wire:key="rt-country-online-{{ $row['country'] ?? 'xx' }}">
                                    <span class="an:min-w-0 an:truncate an:text-[14px] an:font-medium {{ $ink }}"><x-analytics::country :code="$row['country']" /></span>
                                    <span class="an:shrink-0 an:text-[14px] an:font-medium {{ $ink }}">{{ NumberLabel::for($row['online']) }}</span>
                                </div>
                            @empty
                                <p class="an:py-4 an:text-[13px] {{ $inkSoft }}">{{ __('Personne en ligne actuellement.') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="an:border-t an:border-subtle"></div>

                {{-- Traffic source and device --}}
                <div class="an:grid an:grid-cols-1 an:md:grid-cols-2 an:md:divide-x an:md:divide-[color:var(--color-gray-100)] an:dark:md:divide-gray-800">
                    <div class="an:p-5">
                        <p class="an:mb-3 an:text-[14px] an:font-medium {{ $inkMuted }}">{{ __('Sources de trafic') }}</p>
                        @if ($sources['total'] > 0)
                            <div class="an:flex an:items-center an:gap-5">
                                <x-analytics::live-donut
                                    :labels="$sources['labels']"
                                    :values="$sources['values']"
                                    :colors="$sources['colors']"
                                    :total="NumberLabel::for($sources['count'])"
                                    :caption="$sources['count'] > 1 ? __('sources') : __('source')"
                                    size="an:h-24 an:w-24"
                                    channel="sources" />
                                <div class="an:flex-1 an:space-y-2.5">
                                    @foreach ($sources['labels'] as $index => $label)
                                        <div wire:key="rt-source-{{ $label }}">
                                            <span class="an:flex an:items-center an:gap-2 an:text-[13px] {{ $inkSoft }}"><span class="an:h-2 an:w-2 an:shrink-0 an:rounded-full" style="background:{{ $sources['colors'][$index] }}"></span>{{ $label }}</span>
                                            <span class="an:block an:pl-4 an:text-[13px]"><span class="an:font-bold {{ $ink }}">{{ NumberLabel::percent($sources['values'][$index] / $sources['total'] * 100) }}</span> <span class="{{ $inkMuted }}">· {{ NumberLabel::for($sources['values'][$index]) }}</span></span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <p class="an:py-4 an:text-[13px] {{ $inkSoft }}">{{ __('Aucune session.') }}</p>
                        @endif
                    </div>
                    <div class="an:border-t an:border-subtle an:p-5 an:md:border-t-0">
                        <p class="an:mb-3 an:text-[14px] an:font-medium {{ $inkMuted }}">{{ __('Appareil') }}</p>
                        @if ($devices['total'] > 0)
                            <div class="an:flex an:items-center an:gap-5">
                                <x-analytics::live-donut
                                    :labels="$devices['labels']"
                                    :values="$devices['values']"
                                    :colors="$devices['colors']"
                                    :total="NumberLabel::for($devices['count'])"
                                    :caption="$devices['count'] > 1 ? __('types') : __('type')"
                                    size="an:h-24 an:w-24"
                                    channel="devices" />
                                <div class="an:flex-1 an:space-y-2.5">
                                    @foreach ($devices['labels'] as $index => $label)
                                        <div wire:key="rt-device-{{ $label }}">
                                            <span class="an:flex an:items-center an:gap-2 an:text-[13px] {{ $inkSoft }}"><span class="an:h-2 an:w-2 an:shrink-0 an:rounded-full" style="background:{{ $devices['colors'][$index] }}"></span>{{ $label }}</span>
                                            <span class="an:block an:pl-4 an:text-[13px]"><span class="an:font-bold {{ $ink }}">{{ NumberLabel::percent($devices['values'][$index] / $devices['total'] * 100) }}</span> <span class="{{ $inkMuted }}">· {{ NumberLabel::for($devices['values'][$index]) }}</span></span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <p class="an:py-4 an:text-[13px] {{ $inkSoft }}">{{ __('Aucune session.') }}</p>
                        @endif
                    </div>
                </div>

                <div class="an:border-t an:border-subtle"></div>

                {{-- Pageviews: a full-width section of its own, proportion bars --}}
                <div class="an:p-5">
                    <p class="an:mb-3 an:text-[14px] an:font-medium {{ $inkMuted }}">{{ __('Pages vues') }}</p>
                    @forelse ($topPages as $item)
                        @php $pct = $maxPages > 0 ? round($item['total'] / $maxPages * 100) : 0; @endphp
                        <div class="an:py-1.5" wire:key="rt-page-{{ md5($item['url']) }}">
                            <div class="an:flex an:items-center an:gap-3">
                                <span class="an:min-w-0 an:flex-1 an:truncate an:text-[14px] {{ $ink }} an:sm:w-56 an:sm:flex-none"><x-analytics::page-url :url="$item['url']" /></span>
                                <div class="an:relative an:hidden an:h-1.5 an:flex-1 an:overflow-hidden an:rounded-full an:bg-elevated an:sm:block">
                                    <div class="an:absolute an:inset-y-0 an:left-0 an:rounded-full an:bg-accent/70" style="width: {{ $pct }}%"></div>
                                </div>
                                <span class="an:w-10 an:shrink-0 an:text-right an:text-[13px] an:font-medium {{ $ink }}">{{ NumberLabel::for($item['total']) }}</span>
                            </div>
                            <div class="an:relative an:mt-1.5 an:h-1.5 an:w-full an:overflow-hidden an:rounded-full an:bg-elevated an:sm:hidden">
                                <div class="an:absolute an:inset-y-0 an:left-0 an:rounded-full an:bg-accent/70" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="an:py-4 an:text-[13px] {{ $inkSoft }}">{{ __('Aucune page vue sur la fenêtre.') }}</p>
                    @endforelse
                </div>
            </div>

            <x-ui::card>
                <div class="an:mb-3 an:flex an:flex-wrap an:items-baseline an:justify-between an:gap-3">
                    <p class="an:text-[16px] an:font-bold {{ $ink }}">{{ __('Activité par minute') }}</p>
                    <div class="an:flex an:items-baseline an:gap-x-6">
                        <span class="an:text-[12px] {{ $inkMuted }}">{{ __('Pages vues') }} <span class="an:text-[14px] an:font-bold {{ $ink }}">{{ NumberLabel::for($window['pageviews']) }}</span></span>
                        <span class="an:text-[12px] {{ $inkMuted }}">{{ __('Conversions') }} <span class="an:text-[14px] an:font-bold {{ $ink }}">{{ NumberLabel::for($conversionsCount) }}</span></span>
                        <span class="an:text-[11px] {{ $inkMuted }}">{{ __('pic :count/min', ['count' => NumberLabel::for($peakMinute)]) }}</span>
                    </div>
                </div>
                <x-analytics::live-line :labels="array_keys($minuteSeries)" :values="array_values($minuteSeries)" channel="pulse" color="--an-accent" height="an:h-48" />
            </x-ui::card>
        </div>

        <div class="an:space-y-6 an:lg:col-span-1">

            {{-- Recent visitors (last 24 hours) --}}
            <div class="an:rounded-xl an:border an:border-default an:bg-surface">
                <div class="an:px-5 an:py-4">
                    <p class="an:text-[16px] an:font-bold {{ $ink }}">{{ __('Dernières sessions') }}</p>
                    <p class="an:mt-0.5 an:text-[12px] {{ $inkMuted }}">{{ __('24 dernières heures') }}</p>
                </div>
                <div class="an:border-t an:border-subtle"></div>

                @if ($recentVisitors === [])
                    <p class="an:px-5 an:py-10 an:text-center an:text-[13px] {{ $inkSoft }}">{{ __('Aucun visiteur sur les 24 dernières heures.') }}</p>
                @else
                    <ul class="an:max-h-[24rem] an:divide-y an:divide-[color:var(--color-gray-100)] an:overflow-y-auto an:dark:divide-gray-800">
                        @foreach ($recentVisitors as $visitor)
                            <li wire:key="rt-session-{{ $visitor->sessionId }}">
                                <a @if ($mayOpenSessions) href="{{ route('analytics.admin.sessions.show', $visitor->sessionId) }}" @endif @class(['an:flex an:items-center an:gap-3 an:px-5 an:py-3', 'an:cursor-pointer an:transition-colors an:hover:bg-elevated/50' => $mayOpenSessions])>
                                    <x-ui::icon :name="$visitor->deviceIcon" class="an:h-5 an:w-5 an:shrink-0 {{ $inkSoft }}" />
                                    <span class="an:min-w-0 an:flex-1">
                                        <span class="an:flex an:items-center an:gap-x-1.5">
                                            <span class="an:truncate an:text-[14px] an:font-medium {{ $ink }}">{{ $visitor->name }}</span>
                                            @if ($visitor->isOnline)
                                                <span class="an:h-1.5 an:w-1.5 an:shrink-0 an:rounded-full an:bg-online"></span>
                                            @endif
                                        </span>
                                        <span class="an:block an:truncate an:text-[12px] {{ $inkMuted }}">
                                            {{ $visitor->lastSeen }}@if ($visitor->city !== null) · {{ $visitor->city }}@endif
                                        </span>
                                    </span>
                                    @if ($mayOpenSessions)
                                        <span class="an:flex an:h-8 an:w-8 an:shrink-0 an:items-center an:justify-center an:rounded-full an:border an:border-accent/30 an:text-accent">
                                            <x-ui::icon name="chevron-right" class="an:h-4 an:w-4" />
                                        </span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Live activity (the realtime window) --}}
            <div class="an:rounded-xl an:border an:border-default an:bg-surface">
                <div class="an:px-5 an:py-4">
                    <p class="an:text-[16px] an:font-bold {{ $ink }}">{{ __('Activité en direct') }}</p>
                    <p class="an:mt-0.5 an:text-[12px] {{ $inkMuted }}">{{ trans_choice('{1} Dernière minute|[2,*] :count dernières minutes', $windowMinutes) }}</p>
                </div>
                <div class="an:border-t an:border-subtle"></div>

                @if ($feed === [])
                    <p class="an:px-5 an:py-10 an:text-center an:text-[13px] {{ $inkSoft }}">{{ trans_choice('{1} Aucune activité sur la dernière minute.|[2,*] Aucune activité sur les :count dernières minutes.', $windowMinutes) }}</p>
                @else
                    <ul class="an:max-h-[24rem] an:divide-y an:divide-[color:var(--color-gray-100)] an:overflow-y-auto an:dark:divide-gray-800">
                        @foreach ($feed as $entry)
                            <li wire:key="rt-feed-{{ $entry->id }}">
                                <a @if ($mayOpenSessions) href="{{ route('analytics.admin.sessions.show', $entry->sessionId) }}" @endif
                                   @class(['an:flex an:items-start an:gap-3 an:px-5 an:py-3', 'an:cursor-pointer an:transition-colors an:hover:bg-elevated/50' => $mayOpenSessions])>
                                    <span class="an:mt-0.5 an:flex an:h-7 an:w-7 an:shrink-0 an:items-center an:justify-center an:rounded-lg {{ $entry->isConversion ? 'an:bg-online/15' : 'an:bg-elevated' }}">
                                        <x-ui::icon :name="$entry->icon" class="an:h-3.5 an:w-3.5 {{ $entry->isConversion ? 'an:text-online-strong' : $inkSoft }}" />
                                    </span>
                                    <span class="an:min-w-0 an:flex-1">
                                        <span class="an:block an:truncate an:text-[14px] an:font-medium {{ $entry->isConversion ? 'an:text-online-strong' : $ink }}">
                                            {{ $entry->action }}@if ($entry->url !== null) · <x-analytics::page-url :url="$entry->url" />@endif
                                        </span>
                                        <span class="an:block an:truncate an:text-[12px] {{ $inkMuted }}">{{ $entry->name }} · {{ $entry->occurredAt }}</span>
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
</x-analytics::root>
