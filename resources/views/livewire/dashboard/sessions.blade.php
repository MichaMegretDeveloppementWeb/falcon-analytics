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

    // Magnitude only: the stat-card arrow + colour convey the direction (see the
    // overview note). Hidden without a baseline or when it rounds to 0 %.
    $deltaLabel = function ($metric): ?string {
        if (! $metric->hasBaseline()) {
            return null;
        }

        $pct = (int) round($metric->changePercent());

        return $pct === 0 ? null : number_format(abs($pct), 0, ',', ' ')."\u{00A0}%";
    };

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

    <x-ui.page-header :title="__('Sessions')" :description="$sessionsCount">
        @include('analytics::livewire.dashboard.partials.filters')
    </x-ui.page-header>

    {{-- Engagement stats --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-ui.stat-card :label="__('Sessions')" :value="number_format($headline['sessions']->current, 0, ',', ' ')" icon="cursor-arrow-rays"
            :trend="$deltaLabel($headline['sessions'])" :trendUp="$headline['sessions']->increased()">
            <div wire:key="spark-s-sessions-{{ $period }}-{{ $subject }}" class="mt-3"><x-analytics::sparkline :values="$sparklines['sessions']" /></div>
        </x-ui.stat-card>
        <x-ui.stat-card :label="__('Durée moy. session')" :value="$formatSeconds($headline['avgSeconds']->current)" icon="clock"
            :trend="$deltaLabel($headline['avgSeconds'])" :trendUp="$headline['avgSeconds']->increased()">
            <div wire:key="spark-s-duration-{{ $period }}-{{ $subject }}" class="mt-3"><x-analytics::sparkline :values="$sparklines['avgSeconds']" /></div>
        </x-ui.stat-card>
        <x-ui.stat-card :label="__('Pages par session')" :value="number_format($headline['pagesPerSession']->current, 1, ',', ' ')" icon="rectangle-stack"
            :trend="$deltaLabel($headline['pagesPerSession'])" :trendUp="$headline['pagesPerSession']->increased()">
            <div wire:key="spark-s-pps-{{ $period }}-{{ $subject }}" class="mt-3"><x-analytics::sparkline :values="$sparklines['pagesPerSession']" /></div>
        </x-ui.stat-card>
        <x-ui.stat-card :label="__('Taux de rebond')" :value="$percent($headline['bounceRate']->current)" icon="arrow-uturn-left"
            :trend="$deltaLabel($headline['bounceRate'])" :trendUp="! $headline['bounceRate']->increased()">
            <div wire:key="spark-s-bounce-{{ $period }}-{{ $subject }}" class="mt-3"><x-analytics::sparkline :values="$sparklines['bounceRate']" /></div>
        </x-ui.stat-card>
    </div>

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
                                    <a href="{{ $sessionUrl }}" class="text-[13px] font-medium text-primary hover:underline">{{ $subjectName ?? $subjectLabel.' #'.$session->subject_id }}</a>
                                    <span class="text-[11px] text-muted">@if ($subjectName){{ $subjectLabel }} · @endif{{ substr($session->visitor?->uuid ?? '', 0, 8) }}</span>
                                @else
                                    <a href="{{ $sessionUrl }}" class="text-[13px] text-secondary hover:underline">{{ __('Anonyme') }}</a>
                                    <span class="text-[11px] text-muted">{{ substr($session->visitor?->uuid ?? '', 0, 8) }}</span>
                                @endif
                            </div>
                        </x-ui.table.cell>
                        <x-ui.table.cell class="whitespace-nowrap">{{ $session->started_at->translatedFormat('d M, H:i') }}</x-ui.table.cell>
                        <x-ui.table.cell class="whitespace-nowrap">{{ $duration }}</x-ui.table.cell>
                        <x-ui.table.cell class="tabular-nums">{{ $session->pageview_count }}</x-ui.table.cell>
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
