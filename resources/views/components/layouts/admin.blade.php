{{--
    The package's own shell: the one that answers when the host names no layout.

    It mounts the kit's layout, writes no document of its own, and hands it only
    what belongs to the package: its navigation and its title. The head, the two
    asset slots, the theme class and the notification container come from the
    kit — and the last one is the piece a hand-written layout forgets without
    any error showing, no notification ever appearing.
--}}
@props(['title' => null])

@php
    use Falcon\Analytics\Enums\Authorization\Ability;
@endphp

<x-ui::layouts.admin :title="$title ?? __('Audience')">

    <x-slot:sidebar>
        <x-ui::sidebar :brand="__('Audience')" brand-icon="chart-bar">
            @canany([Ability::Overview, Ability::Realtime, Ability::Visitors, Ability::Sessions, Ability::Events, Ability::Funnels, Ability::Integrations])
                <x-ui::sidebar.group :label="__('Audience')">
                    @can(Ability::Overview)
                        <x-ui::sidebar.link
                            :href="route('analytics.admin.overview')"
                            icon="chart-pie"
                            :active="request()->routeIs('analytics.admin.overview')">
                            {{ __('Vue d\'ensemble') }}
                        </x-ui::sidebar.link>
                    @endcan

                    @can(Ability::Realtime)
                        <x-ui::sidebar.link
                            :href="route('analytics.admin.realtime')"
                            icon="signal"
                            :active="request()->routeIs('analytics.admin.realtime')">
                            {{ __('Temps réel') }}
                        </x-ui::sidebar.link>
                    @endcan

                    @can(Ability::Visitors)
                        <x-ui::sidebar.link
                            :href="route('analytics.admin.visitors')"
                            icon="user-group"
                            :active="request()->routeIs('analytics.admin.visitors*')">
                            {{ __('Visiteurs') }}
                        </x-ui::sidebar.link>
                    @endcan

                    @can(Ability::Sessions)
                        <x-ui::sidebar.link
                            :href="route('analytics.admin.sessions')"
                            icon="users"
                            :active="request()->routeIs('analytics.admin.sessions*')">
                            {{ __('Sessions') }}
                        </x-ui::sidebar.link>
                    @endcan

                    @can(Ability::Events)
                        <x-ui::sidebar.link
                            :href="route('analytics.admin.events')"
                            icon="bolt"
                            :active="request()->routeIs('analytics.admin.events')">
                            {{ __('Événements') }}
                        </x-ui::sidebar.link>
                    @endcan

                    @can(Ability::Funnels)
                        <x-ui::sidebar.link
                            :href="route('analytics.admin.funnels')"
                            icon="funnel"
                            :active="request()->routeIs('analytics.admin.funnels')">
                            {{ __('Tunnels') }}
                        </x-ui::sidebar.link>
                    @endcan

                    {{-- Integrations have nothing to show until Google credentials
                         are configured. --}}
                    @if (trim((string) config('analytics.search_console.client_id')) !== '')
                        @can(Ability::Integrations)
                            <x-ui::sidebar.link
                                :href="route('analytics.admin.integrations')"
                                icon="puzzle-piece"
                                :active="request()->routeIs('analytics.admin.integrations*')">
                                {{ __('Intégrations') }}
                            </x-ui::sidebar.link>
                        @endcan
                    @endif
                </x-ui::sidebar.group>
            @endcanany

            @canany([Ability::MarketingDashboard, Ability::Campaigns, Ability::Ads])
                <x-ui::sidebar.group :label="__('Marketing')">
                    @can(Ability::MarketingDashboard)
                        <x-ui::sidebar.link
                            :href="route('analytics.admin.marketing.dashboard')"
                            icon="presentation-chart-line"
                            :active="request()->routeIs('analytics.admin.marketing.dashboard')">
                            {{ __('Vue d\'ensemble') }}
                        </x-ui::sidebar.link>
                    @endcan

                    @can(Ability::Campaigns)
                        <x-ui::sidebar.link
                            :href="route('analytics.admin.marketing.campaigns')"
                            icon="megaphone"
                            :active="request()->routeIs('analytics.admin.marketing.campaigns*')">
                            {{ __('Campagnes') }}
                        </x-ui::sidebar.link>
                    @endcan

                    @can(Ability::Ads)
                        <x-ui::sidebar.link
                            :href="route('analytics.admin.marketing.ads')"
                            icon="cursor-arrow-rays"
                            :active="request()->routeIs('analytics.admin.marketing.ads*')">
                            {{ __('Publicités') }}
                        </x-ui::sidebar.link>
                    @endcan
                </x-ui::sidebar.group>
            @endcanany
        </x-ui::sidebar>
    </x-slot:sidebar>

    <x-slot:head>
        {{-- A private administration surface: kept out of search engines. --}}
        <meta name="robots" content="noindex, nofollow">

        @livewireStyles
    </x-slot:head>

    {{ $slot }}

    <x-slot:scripts>
        @livewireScripts
    </x-slot:scripts>
</x-ui::layouts.admin>
