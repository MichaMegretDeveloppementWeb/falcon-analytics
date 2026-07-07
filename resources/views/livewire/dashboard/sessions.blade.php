@php
    use Falcon\Analytics\Support\DeviceLabel;
    use Illuminate\Support\Str;

    $subjectResolver = app(\Falcon\Analytics\Services\SubjectResolver::class);

    $formatSeconds = function (float $seconds): string {
        $total = (int) round($seconds);
        $minutes = intdiv($total, 60);
        $rest = $total % 60;

        if ($minutes > 0) {
            return $rest > 0 ? "{$minutes}\u{00A0}min\u{00A0}{$rest}\u{00A0}s" : "{$minutes}\u{00A0}min";
        }

        return "{$total}\u{00A0}s";
    };

    $percent = fn ($v): string => number_format((float) $v, 1, ',', ' ')."\u{00A0}%";

    $sessionsTotal = number_format($sessions->total(), 0, ',', ' ');
    $sessionsCount = $sessions->total() <= 1
        ? __(':count session', ['count' => $sessionsTotal])
        : __(':count sessions', ['count' => $sessionsTotal]);

    $deviceOptions = ['' => __('Tous les appareils')];
    foreach ($filterOptions['devices'] as $deviceType) {
        $deviceOptions[$deviceType] = DeviceLabel::for($deviceType);
    }

    $sourceLabels = ['direct' => 'Direct', 'organic' => 'Naturel', 'social' => 'Réseaux sociaux', 'paid' => 'Payant', 'referral' => 'Référent', 'email' => 'E-mail', 'campaign' => 'Campagne'];
    $sourceOptions = ['' => __('Toutes les sources')];
    foreach ($filterOptions['sources'] as $sourceName) {
        $sourceOptions[$sourceName] = __($sourceLabels[strtolower($sourceName)] ?? Str::headline($sourceName));
    }
@endphp

<div class="space-y-6">

    @include('analytics::livewire.dashboard.partials.tooltip-host')

    <x-ui.page-header :title="__('Sessions')" :description="$sessionsCount">
        @include('analytics::livewire.dashboard.partials.filters')
    </x-ui.page-header>

    {{-- Engagement stats (deferred) --}}
    <livewire:analytics-sessions-headline :period="$period" :subject="$subject" :key="'sessions-headline-'.$period.'-'.$subject" />

    {{-- Toolbar --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="w-full sm:max-w-xs">
            <x-ui.search-input wire:model.live.debounce.300ms="search" :placeholder="__('Rechercher un nom, une ville, un pays, un ID…')" class="w-full" />
        </div>
        <div class="flex items-center gap-2">
            @if (count($deviceOptions) > 1)
                <div class="w-40"><x-ui.select wire:model.live="device" :options="$deviceOptions" /></div>
            @endif
            @if (count($sourceOptions) > 1)
                <div class="w-40"><x-ui.select wire:model.live="source" :options="$sourceOptions" /></div>
            @endif
        </div>
    </div>

    @if ($sessions->isEmpty())
        <x-ui.empty-state
            icon="users"
            :title="__('Aucune session')"
            :description="__('Aucune session ne correspond aux filtres.')" />
    @else
        <x-ui.table>
            <x-ui.table.head>
                <x-ui.table.header-cell :first="true">{{ __('Visiteur') }}</x-ui.table.header-cell>
                <x-ui.table.header-cell>@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'started_at', 'label' => __('Début')])</x-ui.table.header-cell>
                <x-ui.table.header-cell>@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'duration', 'label' => __('Durée')])</x-ui.table.header-cell>
                <x-ui.table.header-cell>@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'pageview_count', 'label' => __('Pages')])</x-ui.table.header-cell>
                <x-ui.table.header-cell>{{ __('Événements') }}</x-ui.table.header-cell>
                <x-ui.table.header-cell>{{ __('Conv.') }}</x-ui.table.header-cell>
                <x-ui.table.header-cell>@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'source', 'label' => __('Source')])</x-ui.table.header-cell>
                <x-ui.table.header-cell>@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'landing_route', 'label' => __('Page d\'entrée')])</x-ui.table.header-cell>
                <x-ui.table.header-cell>@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'device_type', 'label' => __('Appareil')])</x-ui.table.header-cell>
                <x-ui.table.header-cell :last="true">@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'country', 'label' => __('Localité')])</x-ui.table.header-cell>
            </x-ui.table.head>
            <x-ui.table.body>
                @foreach ($sessions as $session)
                    @php
                        $duration = $formatSeconds((int) $session->started_at->diffInSeconds($session->last_activity_at));
                    @endphp
                    @php $sessionUrl = route('analytics.sessions.show', $session); @endphp
                    <x-ui.table.row
                        wire:key="session-{{ $session->id }}"
                        onclick="if (!event.target.closest('a')) window.location='{{ $sessionUrl }}'"
                        class="cursor-pointer">
                        <x-ui.table.cell :first="true" variant="primary">
                            <div class="flex flex-col">
                                @if ($session->subject_type)
                                    @php
                                        $subjectName = $subjectNames[$session->subject_type.':'.$session->subject_id] ?? null;
                                        $subjectLabel = $subjectResolver->label($session->subject_type);
                                    @endphp
                                    <a href="{{ $sessionUrl }}" class="cursor-pointer text-[13px] font-medium text-primary hover:underline">{{ $subjectName ?? $subjectLabel.' #'.$session->subject_id }}</a>
                                    <span class="text-[11px] text-muted">@if ($subjectName){{ $subjectLabel }} · @endif{{ substr($session->visitor?->uuid ?? '', 0, 8) }}</span>
                                @else
                                    <a href="{{ $sessionUrl }}" class="cursor-pointer text-[13px] font-medium text-primary hover:underline">{{ __('Visiteur #:id', ['id' => $session->visitor_id]) }}</a>
                                    <span class="text-[11px] text-muted">{{ substr($session->visitor?->uuid ?? '', 0, 8) }}</span>
                                @endif
                            </div>
                        </x-ui.table.cell>
                        <x-ui.table.cell class="whitespace-nowrap">{{ $session->started_at->translatedFormat('d M, H:i') }}</x-ui.table.cell>
                        <x-ui.table.cell class="whitespace-nowrap">{{ $duration }}</x-ui.table.cell>
                        <x-ui.table.cell class="tabular-nums">{{ $session->pageview_count }}</x-ui.table.cell>
                        <x-ui.table.cell class="tabular-nums text-secondary">{{ $session->events_count }}</x-ui.table.cell>
                        <x-ui.table.cell class="tabular-nums font-medium {{ $session->conversions_count > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted' }}">{{ $session->conversions_count }}</x-ui.table.cell>
                        <x-ui.table.cell>
                            @if ($session->source)
                                <x-ui.badge color="gray"><x-analytics::source :value="$session->source" /></x-ui.badge>
                            @else
                                <span class="text-muted">{{ __('Directe') }}</span>
                            @endif
                        </x-ui.table.cell>
                        <x-ui.table.cell class="whitespace-nowrap">
                            @if ($session->landing_route || $session->landing_url)
                                <x-analytics::page-url :route="$session->landing_route" :url="$session->landing_url" />
                            @else
                                <span class="text-muted">·</span>
                            @endif
                        </x-ui.table.cell>
                        <x-ui.table.cell class="whitespace-nowrap">
                            @if ($session->device_type || $session->browser)
                                {{ $session->device_type ? DeviceLabel::for($session->device_type) : __('Inconnu') }}@if ($session->browser) · {{ $session->browser }}@endif
                            @else
                                <span class="text-muted">{{ __('Inconnu') }}</span>
                            @endif
                        </x-ui.table.cell>
                        <x-ui.table.cell :last="true" class="whitespace-nowrap">
                            @if ($session->country || $session->city)
                                <x-analytics::country :code="$session->country" :city="$session->city" />
                            @else
                                <span class="text-muted">{{ __('Inconnu') }}</span>
                            @endif
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @endforeach
            </x-ui.table.body>
        </x-ui.table>

        @if ($sessions->hasPages())
            <div class="mt-6"><x-ui.pagination :paginator="$sessions" mode="livewire" /></div>
        @endif
    @endif

</div>
