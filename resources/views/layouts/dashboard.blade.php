@php
    $routeName = config('analytics.dashboard.route_name', 'analytics');
    $marketingRouteName = config('analytics.marketing.route_name', 'marketing');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" {{ falcon_html() }}>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Private admin surface: keep it out of search engines --}}
    <meta name="robots" content="noindex, nofollow">

    <title>{{ $analyticsTitle ?? __('Analytics') }}</title>

    {{-- Pas de `@vite` ici, et c'est impossible : le paquet ne connait pas le
         nom des entrees de l'hote. Nommez votre propre layout dans la
         configuration, ou publiez celui-ci et ajoutez-y les votres.

         `@falconStyles` avant tout le reste · l'ordre des couches se joue a la
         premiere feuille lue, et le kit doit passer avant. --}}
    @falconStyles

    @livewireStyles
</head>
<body class="min-h-full antialiased">

    <x-ui::sidebar :brand="__('Analytics')" brand-icon="chart-bar">
        <x-ui::sidebar.group :label="__('Analytics')">
            <x-ui::sidebar.link
                :href="route($routeName.'.overview')"
                icon="chart-pie"
                :active="request()->routeIs($routeName.'.overview')">
                {{ __('Vue d\'ensemble') }}
            </x-ui::sidebar.link>

            <x-ui::sidebar.link
                :href="route($routeName.'.realtime')"
                icon="signal"
                :active="request()->routeIs($routeName.'.realtime')">
                {{ __('Temps réel') }}
            </x-ui::sidebar.link>

            <x-ui::sidebar.link
                :href="route($routeName.'.visitors')"
                icon="user-group"
                :active="request()->routeIs($routeName.'.visitors*')">
                {{ __('Visiteurs') }}
            </x-ui::sidebar.link>

            <x-ui::sidebar.link
                :href="route($routeName.'.sessions')"
                icon="users"
                :active="request()->routeIs($routeName.'.sessions*')">
                {{ __('Sessions') }}
            </x-ui::sidebar.link>

            <x-ui::sidebar.link
                :href="route($routeName.'.events')"
                icon="bolt"
                :active="request()->routeIs($routeName.'.events')">
                {{ __('Événements') }}
            </x-ui::sidebar.link>

            <x-ui::sidebar.link
                :href="route($routeName.'.funnels')"
                icon="funnel"
                :active="request()->routeIs($routeName.'.funnels')">
                {{ __('Tunnels') }}
            </x-ui::sidebar.link>

            @if (trim((string) config('analytics.search_console.client_id')) !== '')
                <x-ui::sidebar.link
                    :href="route($routeName.'.integrations')"
                    icon="puzzle-piece"
                    :active="request()->routeIs($routeName.'.integrations*')">
                    {{ __('Intégrations') }}
                </x-ui::sidebar.link>
            @endif
        </x-ui::sidebar.group>

        <x-ui::sidebar.group :label="__('Marketing')">
            <x-ui::sidebar.link
                :href="route($marketingRouteName.'.dashboard')"
                icon="presentation-chart-line"
                :active="request()->routeIs($marketingRouteName.'.dashboard')">
                {{ __('Vue d\'ensemble') }}
            </x-ui::sidebar.link>

            <x-ui::sidebar.link
                :href="route($marketingRouteName.'.campaigns')"
                icon="megaphone"
                :active="request()->routeIs($marketingRouteName.'.campaigns*')">
                {{ __('Campagnes') }}
            </x-ui::sidebar.link>

            <x-ui::sidebar.link
                :href="route($marketingRouteName.'.ads')"
                icon="cursor-arrow-rays"
                :active="request()->routeIs($marketingRouteName.'.ads*')">
                {{ __('Pubs') }}
            </x-ui::sidebar.link>
        </x-ui::sidebar.group>
    </x-ui::sidebar>

    <div class="flex min-h-full flex-col lg:pl-[62px] wide:pl-[260px]">

        <header class="flex h-14 shrink-0 items-center justify-between border-b border-base bg-surface px-4 sm:px-6">
            <div class="flex items-center gap-x-3">
                <x-ui::sidebar.trigger />
                <span class="text-[13px] font-medium text-secondary">{{ __('Analytics') }}</span>
            </div>
            <div class="flex items-center gap-x-3">
                <x-ui::theme-toggle variant="switch" />
            </div>
        </header>

        <main class="flex-1">
            <div class="mx-auto max-w-[90em] px-4 py-6 sm:px-6 sm:py-8">
                @yield('content')
            </div>
        </main>
    </div>

    {{-- `@falconScripts` rend le script du kit et pose le conteneur des
         notifications s'il manque · celui ci-dessus le place ou on le veut,
         donc la directive n'en ajoutera pas un second. --}}
    <x-ui::toast position="top-right" />

    @falconScripts

    @livewireScripts
</body>
</html>
