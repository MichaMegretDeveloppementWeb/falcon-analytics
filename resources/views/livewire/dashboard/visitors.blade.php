@php
    use Falcon\Analytics\Support\NumberLabel;
@endphp

<x-analytics::root area="admin" class="an:space-y-6">

    @include('analytics::livewire.dashboard.partials.tooltip-host')

    @php
        $visitorsTotal = NumberLabel::for($visitors->total());
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

    {{-- Why a locality reads as unknown: absent database, truncated one, or private address. --}}
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

    {{-- All-time directory --}}
    <x-ui::section-header :title="__('Tous les visiteurs')" class="an:pt-2" />

    <x-ui::search-input wire:model.live.debounce.300ms="search" :placeholder="__('Rechercher un nom, un ID…')" class="an:w-full an:sm:max-w-xs" />

    @if ($visitors->isEmpty())
        <x-ui::empty-state
            icon="users"
            :title="__('Aucun visiteur')"
            :description="__('Aucun visiteur ne correspond aux filtres.')" />
    @else
        <x-ui::table x-data="anCopyList">
            <x-ui::table.head>
                <x-ui::table.header-cell :first="true">{{ __('Visiteur') }}</x-ui::table.header-cell>
                <x-ui::table.header-cell>{{ __('Type') }}</x-ui::table.header-cell>
                <x-ui::table.header-cell>@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'session_count', 'label' => __('Sessions')])</x-ui::table.header-cell>
                <x-ui::table.header-cell>@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'first_seen_at', 'label' => __('Première session')])</x-ui::table.header-cell>
                <x-ui::table.header-cell>@include('analytics::livewire.dashboard.partials.sort-header', ['column' => 'last_seen_at', 'label' => __('Dernière session')])</x-ui::table.header-cell>
                <x-ui::table.header-cell>{{ __('Localité') }}</x-ui::table.header-cell>
                <x-ui::table.header-cell :last="true">{{ __('Source') }}</x-ui::table.header-cell>
            </x-ui::table.head>
            <x-ui::table.body>
                @foreach ($visitors as $visitor)
                    <x-ui::table.row wire:key="visitor-{{ $visitor->id }}" class="an-row-link">
                        <x-ui::table.cell :first="true">
                            <div class="an:flex an:flex-col an:gap-0.5">
                                <a href="{{ route('analytics.admin.visitors.show', $visitor->id) }}" class="an-row-link__target an:cursor-pointer an:text-[13px] an:font-medium an:text-primary an:hover:underline">{{ $visitor->name }}</a>
                                <span class="an:text-[11px] an:text-muted"><x-analytics::row-uuid :uuid="$visitor->uuid" /></span>
                            </div>
                        </x-ui::table.cell>
                        <x-ui::table.cell>
                            @if ($visitor->kind !== null)
                                <x-ui::badge color="blue">{{ $visitor->kind }}</x-ui::badge>
                            @else
                                <x-ui::badge color="gray">{{ __('Anonyme') }}</x-ui::badge>
                            @endif
                        </x-ui::table.cell>
                        <x-ui::table.cell class="an:tabular-nums">{{ NumberLabel::for($visitor->sessionCount) }}</x-ui::table.cell>
                        <x-ui::table.cell class="an:whitespace-nowrap">{{ $visitor->firstSeenAt->translatedFormat('d M Y') }}</x-ui::table.cell>
                        <x-ui::table.cell class="an:whitespace-nowrap an:text-secondary">{{ $visitor->lastSeenAt->diffForHumans() }}</x-ui::table.cell>
                        <x-ui::table.cell>
                            {{-- Bounded: truncates with the full text on hover, so the
                                 table never widens past its container. --}}
                            <div class="an:max-w-44 an:truncate">
                                @if ($visitor->country || $visitor->city)
                                    <x-analytics::country :code="$visitor->country" :city="$visitor->city" />
                                @else
                                    <span class="an:text-muted">{{ __('Inconnu') }}</span>
                                @endif
                            </div>
                        </x-ui::table.cell>
                        <x-ui::table.cell :last="true" class="an:whitespace-nowrap">
                            <x-ui::badge color="gray"><x-analytics::source :value="$visitor->source" /></x-ui::badge>
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
