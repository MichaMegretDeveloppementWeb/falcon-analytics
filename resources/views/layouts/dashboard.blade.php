@php
    $routeName = config('analytics.dashboard.route_name', 'analytics');
    $marketingRouteName = config('analytics.marketing.route_name', 'marketing');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-page {{ falcon_theme_class() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Private admin surface: keep it out of search engines --}}
    <meta name="robots" content="noindex, nofollow">

    {{-- Le titre vient du controleur de l'ecran, qui le connait avant que le
         composant ne se rende. Le repli couvre un hote qui monterait cette vue
         lui-meme. --}}
    <title>{{ $analyticsTitle ?? __('Analytics') }}</title>

    {{-- **Ce gabarit ne porte ni feuille ni script, et il ne peut pas.**

         Le CSS et le JavaScript du back-office sont importes dans les entrees
         de l'hote par `analytics:install`, et c'est son build qui les produit ·
         une page ne porte qu'une feuille Tailwind. Le paquet ne connait pas le
         nom de ces entrees, donc il ne peut pas les charger.

         Ce gabarit est un point de depart, pas une coquille finie. Deux facons
         de s'en servir ·

           - **la bonne** · nommez votre propre gabarit dans
             `analytics.dashboard.layout`, et mettez-y le `@vite` de vos entrees ;
           - publiez celui-ci (`--tag=analytics-views`) et ajoutez votre `@vite`
             dans votre copie, qui est la votre.

         Il portait `@uiKitHead` et
         `@vite(['resources/css/ui-kit.css', 'resources/js/ui-kit.js'])`
         jusqu'au 2026-09-06 · deux noms de fichiers devines chez l'hote, qui
         n'avaient aucune raison d'exister chez lui. --}}

    @livewireStyles
</head>
<body class="min-h-full antialiased">

    <x-ui.sidebar :brand="__('Analytics')" brand-icon="chart-bar">
        <x-ui.sidebar.group :label="__('Analytics')">
            <x-ui.sidebar.link
                :href="route($routeName.'.overview')"
                icon="chart-pie"
                :active="request()->routeIs($routeName.'.overview')">
                {{ __('Vue d\'ensemble') }}
            </x-ui.sidebar.link>

            <x-ui.sidebar.link
                :href="route($routeName.'.realtime')"
                icon="signal"
                :active="request()->routeIs($routeName.'.realtime')">
                {{ __('Temps réel') }}
            </x-ui.sidebar.link>

            <x-ui.sidebar.link
                :href="route($routeName.'.visitors')"
                icon="user-group"
                :active="request()->routeIs($routeName.'.visitors*')">
                {{ __('Visiteurs') }}
            </x-ui.sidebar.link>

            <x-ui.sidebar.link
                :href="route($routeName.'.sessions')"
                icon="users"
                :active="request()->routeIs($routeName.'.sessions*')">
                {{ __('Sessions') }}
            </x-ui.sidebar.link>

            <x-ui.sidebar.link
                :href="route($routeName.'.events')"
                icon="bolt"
                :active="request()->routeIs($routeName.'.events')">
                {{ __('Événements') }}
            </x-ui.sidebar.link>

            <x-ui.sidebar.link
                :href="route($routeName.'.funnels')"
                icon="funnel"
                :active="request()->routeIs($routeName.'.funnels')">
                {{ __('Tunnels') }}
            </x-ui.sidebar.link>

            @if (trim((string) config('analytics.search_console.client_id')) !== '')
                <x-ui.sidebar.link
                    :href="route($routeName.'.integrations')"
                    icon="puzzle-piece"
                    :active="request()->routeIs($routeName.'.integrations*')">
                    {{ __('Intégrations') }}
                </x-ui.sidebar.link>
            @endif
        </x-ui.sidebar.group>

        <x-ui.sidebar.group :label="__('Marketing')">
            <x-ui.sidebar.link
                :href="route($marketingRouteName.'.dashboard')"
                icon="presentation-chart-line"
                :active="request()->routeIs($marketingRouteName.'.dashboard')">
                {{ __('Vue d\'ensemble') }}
            </x-ui.sidebar.link>

            <x-ui.sidebar.link
                :href="route($marketingRouteName.'.campaigns')"
                icon="megaphone"
                :active="request()->routeIs($marketingRouteName.'.campaigns*')">
                {{ __('Campagnes') }}
            </x-ui.sidebar.link>

            <x-ui.sidebar.link
                :href="route($marketingRouteName.'.ads')"
                icon="cursor-arrow-rays"
                :active="request()->routeIs($marketingRouteName.'.ads*')">
                {{ __('Pubs') }}
            </x-ui.sidebar.link>
        </x-ui.sidebar.group>
    </x-ui.sidebar>

    <div class="flex min-h-full flex-col lg:pl-[62px] wide:pl-[260px]">

        <header class="flex h-14 shrink-0 items-center justify-between border-b border-base bg-surface px-4 sm:px-6">
            <div class="flex items-center gap-x-3">
                <x-ui.sidebar.trigger />
                <span class="text-[13px] font-medium text-secondary">{{ __('Analytics') }}</span>
            </div>
            <div class="flex items-center gap-x-3">
                <x-ui.theme-toggle variant="switch" />
            </div>
        </header>

        <main class="flex-1">
            <div class="mx-auto max-w-[90em] px-4 py-6 sm:px-6 sm:py-8">
                @yield('content')
            </div>
        </main>
    </div>

    <x-ui.toast position="top-right" />

    @livewireScripts
</body>
</html>
