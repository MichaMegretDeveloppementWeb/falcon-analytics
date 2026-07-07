@php
    use Illuminate\Support\Str;

    $subjectResolver = app(\Falcon\Analytics\Services\SubjectResolver::class);
@endphp

<div class="space-y-6">

    @include('analytics::livewire.dashboard.partials.tooltip-host')

    <x-ui.page-header
        :title="__('Visiteurs')"
        :description="__('du :from au :to', [
            'from' => $range->from->isoFormat('D MMM YYYY'),
            'to' => $range->to->isoFormat('D MMM YYYY'),
        ])">
        @include('analytics::livewire.dashboard.partials.filters')
    </x-ui.page-header>

    {{-- Headline metrics (deferred) --}}
    <livewire:analytics-visitors-headline :period="$period" :subject="$subject" :key="'visitors-headline-'.$period.'-'.$subject" />

    <x-ui.search-input wire:model.live.debounce.300ms="search" :placeholder="__('Rechercher un nom, un ID…')" class="w-full sm:max-w-xs" />

    @if ($visitors->isEmpty())
        <x-ui.empty-state
            icon="users"
            :title="__('Aucun visiteur')"
            :description="__('Aucun visiteur ne correspond aux filtres.')" />
    @else
        <x-ui.table>
            <x-ui.table.head>
                <x-ui.table.header-cell :first="true">{{ __('Visiteur') }}</x-ui.table.header-cell>
                <x-ui.table.header-cell>{{ __('Type') }}</x-ui.table.header-cell>
                <x-ui.table.header-cell>@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'period_sessions', 'label' => __('Sessions')])</x-ui.table.header-cell>
                <x-ui.table.header-cell>@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'first_seen_at', 'label' => __('Première visite')])</x-ui.table.header-cell>
                <x-ui.table.header-cell>@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'last_seen_at', 'label' => __('Dernière visite')])</x-ui.table.header-cell>
                <x-ui.table.header-cell>{{ __('Localité') }}</x-ui.table.header-cell>
                <x-ui.table.header-cell :last="true">{{ __('Acquisition') }}</x-ui.table.header-cell>
            </x-ui.table.head>
            <x-ui.table.body>
                @foreach ($visitors as $visitor)
                    @php
                        $subjectName = $visitor->subject_type ? ($subjectNames[$visitor->subject_type.':'.$visitor->subject_id] ?? null) : null;
                        $subjectLabel = $visitor->subject_type ? $subjectResolver->label($visitor->subject_type) : null;
                        $visitorUrl = route(config('analytics.dashboard.route_name', 'analytics').'.visitors.show', $visitor);
                    @endphp
                    <x-ui.table.row wire:key="visitor-{{ $visitor->id }}" onclick="if (!event.target.closest('a')) window.location='{{ $visitorUrl }}'" class="cursor-pointer">
                        <x-ui.table.cell :first="true">
                            <div class="flex flex-col gap-0.5">
                                <a href="{{ $visitorUrl }}" class="cursor-pointer text-[13px] font-medium text-primary hover:underline">
                                    @if ($visitor->subject_type)
                                        {{ $subjectName ?? $subjectLabel.' #'.$visitor->subject_id }}
                                    @else
                                        {{ __('Visiteur #:id', ['id' => $visitor->id]) }}
                                    @endif
                                </a>
                                <span class="text-[11px] text-muted">{{ Str::limit($visitor->uuid, 16, '') }}</span>
                            </div>
                        </x-ui.table.cell>
                        <x-ui.table.cell>
                            @if ($visitor->subject_type)
                                <x-ui.badge color="blue">{{ $subjectLabel }}</x-ui.badge>
                            @else
                                <x-ui.badge color="gray">{{ __('Anonyme') }}</x-ui.badge>
                            @endif
                        </x-ui.table.cell>
                        <x-ui.table.cell class="tabular-nums">{{ number_format((int) $visitor->period_sessions, 0, ',', ' ') }}</x-ui.table.cell>
                        <x-ui.table.cell class="whitespace-nowrap">{{ $visitor->first_seen_at->translatedFormat('d M Y') }}</x-ui.table.cell>
                        <x-ui.table.cell class="whitespace-nowrap text-secondary">{{ $visitor->last_seen_at->diffForHumans() }}</x-ui.table.cell>
                        <x-ui.table.cell class="whitespace-nowrap">
                            @if ($visitor->last_country || $visitor->last_city)
                                <x-analytics::country :code="$visitor->last_country" :city="$visitor->last_city" />
                            @else
                                <span class="text-muted">{{ __('Inconnu') }}</span>
                            @endif
                        </x-ui.table.cell>
                        <x-ui.table.cell :last="true" class="whitespace-nowrap">
                            @if ($visitor->acquisition_source)
                                <x-ui.badge color="gray"><x-analytics::source :value="$visitor->acquisition_source" /></x-ui.badge>
                            @else
                                <span class="text-muted">{{ __('Directe') }}</span>
                            @endif
                        </x-ui.table.cell>
                    </x-ui.table.row>
                @endforeach
            </x-ui.table.body>
        </x-ui.table>

        @if ($visitors->hasPages())
            <div class="mt-6"><x-ui.pagination :paginator="$visitors" mode="livewire" /></div>
        @endif
    @endif

</div>
