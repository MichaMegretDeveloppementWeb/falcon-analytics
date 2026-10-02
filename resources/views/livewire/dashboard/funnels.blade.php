<x-analytics::root area="admin" class="an:space-y-8">

    @include('analytics::livewire.dashboard.partials.tooltip-host')

    <x-ui::page-header
        :title="__('Tunnels')"
        :description="__('du :from au :to', [
            'from' => \Falcon\Analytics\Support\DateLabel::for($range->from, 'j M Y'),
            'to' => \Falcon\Analytics\Support\DateLabel::for($range->to, 'j M Y'),
        ])">
        @include('analytics::livewire.dashboard.partials.filters')
    </x-ui::page-header>

    <livewire:analytics::admin.widgets.funnels-content :period="$period" :subject="$subject" :key="'funnels-'.$period.'-'.$subject" />

</x-analytics::root>
