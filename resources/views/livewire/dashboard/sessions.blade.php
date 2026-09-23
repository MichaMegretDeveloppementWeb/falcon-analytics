@php
    use Falcon\Analytics\Support\NumberLabel;

    $sessionsTotal = NumberLabel::for($sessions->total());
    $sessionsCount = $sessions->total() <= 1
        ? __(':count session', ['count' => $sessionsTotal])
        : __(':count sessions', ['count' => $sessionsTotal]);
@endphp

<x-analytics::root area="admin" class="an:space-y-6">

    @include('analytics::livewire.dashboard.partials.tooltip-host')

    <x-ui::page-header :title="__('Sessions')" :description="$sessionsCount">
        @include('analytics::livewire.dashboard.partials.filters')
    </x-ui::page-header>

    {{-- Why a locality reads as unknown: absent database, truncated one, or private address. --}}
    <x-analytics::geo-notice />

    {{-- Engagement stats (deferred) --}}
    <livewire:analytics::admin.widgets.sessions-headline :period="$period" :subject="$subject" :key="'sessions-headline-'.$period.'-'.$subject" />

    {{-- Toolbar --}}
    <div class="an:flex an:flex-col an:gap-3 an:sm:flex-row an:sm:items-center">
        <div class="an:w-full an:sm:max-w-xs">
            <x-ui::search-input wire:model.live.debounce.300ms="search" :placeholder="__('Rechercher un nom, une ville, un pays, un ID…')" class="an:w-full" />
        </div>
        <div class="an:flex an:items-center an:gap-2">
            @if (count($deviceOptions) > 1)
                <div class="an:w-40"><x-ui::select wire:model.live="device" :options="$deviceOptions" /></div>
            @endif
            @if (count($sourceOptions) > 1)
                <div class="an:w-40"><x-ui::select wire:model.live="source" :options="$sourceOptions" /></div>
            @endif
        </div>
    </div>

    @if ($sessions->isEmpty())
        <x-ui::empty-state
            icon="users"
            :title="__('Aucune session')"
            :description="__('Aucune session ne correspond aux filtres.')" />
    @else
        <x-ui::table x-data="anCopyList">
            <x-ui::table.head>
                <x-ui::table.header-cell :first="true">{{ __('Visiteur') }}</x-ui::table.header-cell>
                <x-ui::table.header-cell>@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'started_at', 'label' => __('Début')])</x-ui::table.header-cell>
                <x-ui::table.header-cell>@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'duration', 'label' => __('Durée')])</x-ui::table.header-cell>
                <x-ui::table.header-cell>@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'pageview_count', 'label' => __('Pages vues')])</x-ui::table.header-cell>
                <x-ui::table.header-cell>@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'events_count', 'label' => __('Événements')])</x-ui::table.header-cell>
                <x-ui::table.header-cell>@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'conversions_count', 'label' => __('Conv.')])</x-ui::table.header-cell>
                <x-ui::table.header-cell>@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'source', 'label' => __('Source')])</x-ui::table.header-cell>
                <x-ui::table.header-cell>@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'landing_route', 'label' => __('Page d\'entrée')])</x-ui::table.header-cell>
                <x-ui::table.header-cell>@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'device_type', 'label' => __('Appareil')])</x-ui::table.header-cell>
                <x-ui::table.header-cell :last="true">@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'country', 'label' => __('Localité')])</x-ui::table.header-cell>
            </x-ui::table.head>
            <x-ui::table.body>
                @foreach ($sessions as $session)
                    <x-ui::table.row
                        wire:key="session-{{ $session->id }}"
                        class="an-row-link">
                        <x-ui::table.cell :first="true" variant="primary">
                            <div class="an:flex an:flex-col">
                                <a href="{{ route('analytics.admin.sessions.show', $session->id) }}" class="an-row-link__target an:cursor-pointer an:text-[13px] an:font-medium an:text-primary an:hover:underline">{{ $session->name }}</a>
                                <span class="an:text-[11px] an:text-muted">@if ($session->label !== null){{ $session->label }} · @endif<x-analytics::row-uuid :uuid="$session->visitorUuid" />@if ($session->notConnected) · {{ __('Non connecté') }}@endif</span>
                            </div>
                        </x-ui::table.cell>
                        <x-ui::table.cell class="an:whitespace-nowrap">{{ $session->startedAt->translatedFormat('d M, H:i') }}</x-ui::table.cell>
                        <x-ui::table.cell class="an:whitespace-nowrap">{{ $session->duration }}</x-ui::table.cell>
                        <x-ui::table.cell class="an:tabular-nums">{{ $session->pageviewCount }}</x-ui::table.cell>
                        <x-ui::table.cell class="an:tabular-nums an:text-secondary">{{ $session->eventsCount }}</x-ui::table.cell>
                        <x-ui::table.cell class="an:tabular-nums an:font-medium {{ $session->conversionsCount > 0 ? 'an:text-emerald-600 an:dark:text-emerald-400' : 'an:text-muted' }}">{{ $session->conversionsCount }}</x-ui::table.cell>
                        <x-ui::table.cell>
                            <x-ui::badge color="gray"><x-analytics::source :value="$session->source" /></x-ui::badge>
                        </x-ui::table.cell>
                        {{-- Bounded cells: long values truncate with the full text on
                             hover, so the table never widens past its container. --}}
                        <x-ui::table.cell>
                            <div class="an:max-w-56 an:truncate">
                                @if ($session->landingRoute || $session->landingUrl)
                                    <x-analytics::page-url :route="$session->landingRoute" :url="$session->landingUrl" />
                                @else
                                    <span class="an:text-muted">·</span>
                                @endif
                            </div>
                        </x-ui::table.cell>
                        <x-ui::table.cell>
                            @if ($session->device !== null)
                                <div class="an:max-w-40 an:truncate" data-an-tooltip="{{ $session->device }}">{{ $session->device }}</div>
                            @else
                                <span class="an:text-muted">{{ __('Inconnu') }}</span>
                            @endif
                        </x-ui::table.cell>
                        <x-ui::table.cell :last="true">
                            <div class="an:max-w-44 an:truncate">
                                @if ($session->country || $session->city)
                                    <x-analytics::country :code="$session->country" :city="$session->city" />
                                @else
                                    <span class="an:text-muted">{{ __('Inconnu') }}</span>
                                @endif
                            </div>
                        </x-ui::table.cell>
                    </x-ui::table.row>
                @endforeach
            </x-ui::table.body>
        </x-ui::table>

        @if ($sessions->hasPages())
            <div class="an:mt-6"><x-ui::pagination :paginator="$sessions" mode="livewire" /></div>
        @endif
    @endif

</x-analytics::root>
