@php
    $previous = $range->previous();
@endphp

<div class="space-y-8">

    @include('analytics::livewire.dashboard.partials.tooltip-host')

    <x-ui.page-header
        :title="__('Vue d\'ensemble')"
        :description="__('du :from au :to', ['from' => $range->from->isoFormat('D MMM YYYY'), 'to' => $range->to->isoFormat('D MMM YYYY')])
            .' · '.__('comparé à :from - :to', ['from' => $previous->from->isoFormat('D MMM'), 'to' => $previous->to->isoFormat('D MMM')])">
        @include('analytics::livewire.dashboard.partials.filters')
    </x-ui.page-header>

    {{-- Headline KPIs + engagement stats (deferred) --}}
    <livewire:analytics-overview-headline :period="$period" :subject="$subject" :key="'ov-headline-'.$period.'-'.$subject" />

    {{-- Traffic trend (deferred) --}}
    <livewire:analytics-trend-chart :period="$period" :subject="$subject" :key="'ov-trend-'.$period.'-'.$subject" />

    {{-- Deferred heavy sections : each loads independently after paint --}}
    <div>
        <x-ui.section-header :title="__('Audience')" :description="__('Visiteurs et appareils')" class="mb-4" />
        <livewire:analytics-overview-audience :period="$period" :subject="$subject" :key="'ov-audience-'.$period.'-'.$subject" />
    </div>

    <div>
        <x-ui.section-header :title="__('Acquisition')" :description="__('D\'où viennent les sessions')" class="mb-4" />
        <livewire:analytics-overview-acquisition :period="$period" :subject="$subject" :key="'ov-acq-'.$period.'-'.$subject" />
    </div>

    <div>
        <x-ui.section-header :title="__('Contenu')" :description="__('Pages et clics')" class="mb-4" />
        <livewire:analytics-overview-content :period="$period" :subject="$subject" :key="'ov-content-'.$period.'-'.$subject" />
    </div>

    <livewire:analytics-overview-events :period="$period" :subject="$subject" :key="'ov-events-'.$period.'-'.$subject" />

    {{-- Organic Google queries via Search Console; the whole section stays
         hidden while the host has not configured the OAuth credentials. --}}
    @if (trim((string) config('analytics.search_console.client_id')) !== '')
        <livewire:analytics-overview-search-queries :period="$period" :key="'ov-gsc-'.$period" />
    @endif

</div>
