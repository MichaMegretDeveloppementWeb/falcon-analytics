@php
    $routeName = config('analytics.dashboard.route_name', 'analytics');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-page">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Private admin surface: keep it out of search engines --}}
    <meta name="robots" content="noindex, nofollow">

    <title>{{ $title ?? __('Analytics') }}</title>

    {{-- Theme anti-flash (must run before the stylesheets) --}}
    @uiKitHead

    {{-- Shared design system: Tailwind + DM Sans + dark mode + Chart.js --}}
    @vite(['resources/css/ui-kit.css', 'resources/js/ui-kit.js'])

    @livewireStyles
</head>
<body class="min-h-full antialiased">

    <x-ui.sidebar :brand="__('Analytics')" brand-icon="chart-bar">
        <x-ui.sidebar.group>
            <x-ui.sidebar.link
                :href="route($routeName.'.overview')"
                icon="chart-pie"
                :active="request()->routeIs($routeName.'.overview')">
                {{ __('Vue d\'ensemble') }}
            </x-ui.sidebar.link>

            <x-ui.sidebar.link
                :href="route($routeName.'.funnels')"
                icon="funnel"
                :active="request()->routeIs($routeName.'.funnels')">
                {{ __('Entonnoirs') }}
            </x-ui.sidebar.link>

            <x-ui.sidebar.link
                :href="route($routeName.'.sessions')"
                icon="users"
                :active="request()->routeIs($routeName.'.sessions')">
                {{ __('Sessions') }}
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
                {{ $slot }}
            </div>
        </main>
    </div>

    <x-ui.toast position="top-right" />

    @livewireScripts
</body>
</html>
