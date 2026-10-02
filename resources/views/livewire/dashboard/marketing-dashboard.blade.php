<x-analytics::root area="admin" class="an:space-y-8">

    <x-ui::page-header
        :title="__('Vue d\'ensemble')"
        :description="__('du :from au :to', [
            'from' => \Falcon\Analytics\Support\DateLabel::for($range->from, 'j M Y'),
            'to' => \Falcon\Analytics\Support\DateLabel::for($range->to, 'j M Y'),
        ])">
        @include('analytics::livewire.dashboard.partials.filters')
    </x-ui::page-header>

    <livewire:analytics::admin.widgets.marketing-dashboard-content :period="$period" :subject="$subject" :key="'mkt-dash-'.$period.'-'.$subject" />

</x-analytics::root>
