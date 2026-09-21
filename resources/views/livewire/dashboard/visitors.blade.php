@php
    use Illuminate\Support\Str;

    $subjectResolver = app(\Falcon\Analytics\Services\SubjectResolver::class);
@endphp

<x-analytics::root area="admin" class="an:space-y-6">

    @include('analytics::livewire.dashboard.partials.tooltip-host')

    @php
        $visitorsTotal = number_format($visitors->total(), 0, ',', ' ');
        $visitorsCount = $visitors->total() <= 1
            ? __(':count visiteur', ['count' => $visitorsTotal])
            : __(':count visiteurs', ['count' => $visitorsTotal]);
    @endphp

    {{-- The role filter applies to the whole page; the period, only to the Activity block. --}}
    <x-ui::page-header :title="__('Visiteurs')" :description="$visitorsCount">
        @if (count($subjectOptions) > 1)
            <div class="an:w-40">
                <x-ui::select wire:model.live="subject" :options="$subjectOptions" />
            </div>
        @endif
    </x-ui::page-header>

    {{-- The locality went missing with no way to tell why: absent database, truncated one, or private address. --}}
    <x-analytics::geo-notice />

    {{-- The KPIs are scoped to the period; the list below is all time. --}}
    <div>
        <div class="an:mb-4 an:flex an:flex-wrap an:items-end an:justify-between an:gap-3">
            <x-ui::section-header
                :title="__('Activité')"
                :description="__('du :from au :to', [
                    'from' => $range->from->isoFormat('D MMM YYYY'),
                    'to' => $range->to->isoFormat('D MMM YYYY'),
                ])" />
            <div class="an:w-44">
                <x-ui::select wire:model.live="period" :options="$periodOptions" />
            </div>
        </div>

        <livewire:analytics::admin.widgets.visitors-headline :period="$period" :subject="$subject" :key="'visitors-headline-'.$period.'-'.$subject" />
    </div>

    {{-- Annuaire tous temps --}}
    <x-ui::section-header :title="__('Tous les visiteurs')" class="an:pt-2" />

    <x-ui::search-input wire:model.live.debounce.300ms="search" :placeholder="__('Rechercher un nom, un ID…')" class="an:w-full an:sm:max-w-xs" />

    @if ($visitors->isEmpty())
        <x-ui::empty-state
            icon="users"
            :title="__('Aucun visiteur')"
            :description="__('Aucun visiteur ne correspond aux filtres.')" />
    @else
        <x-ui::table>
            <x-ui::table.head>
                <x-ui::table.header-cell :first="true">{{ __('Visiteur') }}</x-ui::table.header-cell>
                <x-ui::table.header-cell>{{ __('Type') }}</x-ui::table.header-cell>
                <x-ui::table.header-cell>@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'session_count', 'label' => __('Sessions')])</x-ui::table.header-cell>
                <x-ui::table.header-cell>@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'first_seen_at', 'label' => __('Première visite')])</x-ui::table.header-cell>
                <x-ui::table.header-cell>@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'last_seen_at', 'label' => __('Dernière visite')])</x-ui::table.header-cell>
                <x-ui::table.header-cell>{{ __('Localité') }}</x-ui::table.header-cell>
                <x-ui::table.header-cell :last="true">{{ __('Acquisition') }}</x-ui::table.header-cell>
            </x-ui::table.head>
            <x-ui::table.body>
                @foreach ($visitors as $visitor)
                    @php
                        $subjectName = $visitor->subject_type ? ($subjectNames[$visitor->subject_type.':'.$visitor->subject_id] ?? null) : null;
                        $subjectLabel = $visitor->subject_type ? $subjectResolver->label($visitor->subject_type) : null;
                        $visitorUrl = route('analytics.admin.visitors.show', $visitor);
                    @endphp
                    <x-ui::table.row wire:key="visitor-{{ $visitor->id }}" class="an-row-link">
                        <x-ui::table.cell :first="true">
                            <div class="an:flex an:flex-col an:gap-0.5">
                                <a href="{{ $visitorUrl }}" class="an-row-link__target an:cursor-pointer an:text-[13px] an:font-medium an:text-primary an:hover:underline">
                                    @if ($visitor->subject_type)
                                        {{ $subjectName ?? $subjectLabel.' #'.$visitor->subject_id }}
                                    @else
                                        {{ __('Visiteur #:id', ['id' => $visitor->id]) }}
                                    @endif
                                </a>
                                <span class="an:text-[11px] an:text-muted">{{ Str::limit($visitor->uuid, 16, '') }}</span>
                            </div>
                        </x-ui::table.cell>
                        <x-ui::table.cell>
                            @if ($visitor->subject_type)
                                <x-ui::badge color="blue">{{ $subjectLabel }}</x-ui::badge>
                            @else
                                <x-ui::badge color="gray">{{ __('Anonyme') }}</x-ui::badge>
                            @endif
                        </x-ui::table.cell>
                        <x-ui::table.cell class="an:tabular-nums">{{ number_format((int) $visitor->session_count, 0, ',', ' ') }}</x-ui::table.cell>
                        <x-ui::table.cell class="an:whitespace-nowrap">{{ $visitor->first_seen_at->translatedFormat('d M Y') }}</x-ui::table.cell>
                        <x-ui::table.cell class="an:whitespace-nowrap an:text-secondary">{{ $visitor->last_seen_at->diffForHumans() }}</x-ui::table.cell>
                        <x-ui::table.cell>
                            {{-- Bounded: truncates with the full text on hover, so the
                                 table never widens past its container. --}}
                            <div class="an:max-w-44 an:truncate">
                                @if ($visitor->last_country || $visitor->last_city)
                                    <x-analytics::country :code="$visitor->last_country" :city="$visitor->last_city" />
                                @else
                                    <span class="an:text-muted">{{ __('Inconnu') }}</span>
                                @endif
                            </div>
                        </x-ui::table.cell>
                        <x-ui::table.cell :last="true" class="an:whitespace-nowrap">
                            @if ($visitor->acquisition_source)
                                <x-ui::badge color="gray"><x-analytics::source :value="$visitor->acquisition_source" /></x-ui::badge>
                            @else
                                <span class="an:text-muted">{{ __('Directe') }}</span>
                            @endif
                        </x-ui::table.cell>
                    </x-ui::table.row>
                @endforeach
            </x-ui::table.body>
        </x-ui::table>

        @if ($visitors->hasPages())
            <div class="an:mt-6"><x-ui::pagination :paginator="$visitors" mode="livewire" /></div>
        @endif
    @endif

</x-analytics::root>
