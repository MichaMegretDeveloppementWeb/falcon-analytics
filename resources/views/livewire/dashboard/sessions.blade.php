@php
    use Illuminate\Support\Str;

    $sessionsTotal = number_format($sessions->total(), 0, ',', ' ');
    $sessionsCount = $sessions->total() <= 1
        ? __(':count session', ['count' => $sessionsTotal])
        : __(':count sessions', ['count' => $sessionsTotal]);
@endphp

<div class="space-y-6">

    <x-ui.page-header :title="__('Sessions')" :description="$sessionsCount">
        @include('analytics::livewire.dashboard.partials.filters')
    </x-ui.page-header>

    <div class="flex items-center gap-3">
        <div class="w-full sm:max-w-xs">
            <x-ui.search-input wire:model.live.debounce.300ms="search" :placeholder="__('Rechercher une IP ou une ville')" class="w-full" />
        </div>
        <div wire:loading.flex class="items-center gap-x-1.5 text-[12px] text-muted">
            <x-ui.icon name="arrow-path" class="h-3.5 w-3.5 animate-spin" />
            {{ __('Chargement...') }}
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
                <x-ui.table.header-cell>{{ __('Début') }}</x-ui.table.header-cell>
                <x-ui.table.header-cell>{{ __('Durée') }}</x-ui.table.header-cell>
                <x-ui.table.header-cell hidden="sm" align="right">{{ __('Pages') }}</x-ui.table.header-cell>
                <x-ui.table.header-cell hidden="md">{{ __('Appareil') }}</x-ui.table.header-cell>
                <x-ui.table.header-cell hidden="lg">{{ __('Localité') }}</x-ui.table.header-cell>
                <x-ui.table.header-cell hidden="lg" :last="true">{{ __('Source') }}</x-ui.table.header-cell>
            </x-ui.table.head>
            <x-ui.table.body>
                @foreach ($sessions as $session)
                    @php
                        $seconds = (int) $session->started_at->diffInSeconds($session->last_activity_at);
                        $minutes = intdiv($seconds, 60);
                        $duration = $minutes > 0
                            ? trim($minutes.' min '.($seconds % 60 > 0 ? ($seconds % 60).' s' : ''))
                            : $seconds.' s';
                    @endphp
                    <x-ui.table.row wire:key="session-{{ $session->id }}">
                        <x-ui.table.cell :first="true" variant="primary">
                            <div class="flex flex-col">
                                @if ($session->subject_type)
                                    <span class="text-[13px] font-medium text-primary">
                                        {{ Str::headline($session->subject_type) }} #{{ $session->subject_id }}
                                    </span>
                                @else
                                    <span class="text-[13px] text-secondary">{{ __('Anonyme') }}</span>
                                @endif
                                <span class="font-mono text-[11px] text-muted">{{ substr($session->visitor?->uuid ?? '', 0, 8) }}</span>
                            </div>
                        </x-ui.table.cell>
                        <x-ui.table.cell>{{ $session->started_at->translatedFormat('d M, H:i') }}</x-ui.table.cell>
                        <x-ui.table.cell>{{ $duration }}</x-ui.table.cell>
                        <x-ui.table.cell hidden="sm" align="right">{{ $session->pageview_count }}</x-ui.table.cell>
                        <x-ui.table.cell hidden="md">
                            @if ($session->device_type || $session->browser)
                                {{ Str::title($session->device_type ?: __('Inconnu')) }}@if ($session->browser) · {{ $session->browser }}@endif
                            @else
                                <span class="text-muted">{{ __('Inconnu') }}</span>
                            @endif
                        </x-ui.table.cell>
                        <x-ui.table.cell hidden="lg">
                            @if ($session->city || $session->country)
                                {{ collect([$session->city, $session->country])->filter()->join(', ') }}
                            @else
                                <span class="text-muted">{{ __('Inconnu') }}</span>
                            @endif
                        </x-ui.table.cell>
                        <x-ui.table.cell hidden="lg" :last="true">
                            @if ($session->source)
                                <x-ui.badge color="gray">{{ Str::headline($session->source) }}</x-ui.badge>
                            @else
                                <span class="text-muted">{{ __('Directe') }}</span>
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
