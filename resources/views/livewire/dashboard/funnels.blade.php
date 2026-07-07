<div class="space-y-8">

    @include('analytics::livewire.dashboard.partials.tooltip-host')

    <x-ui.page-header
        :title="__('Tunnels')"
        :description="__('du :from au :to', [
            'from' => $range->from->isoFormat('D MMM YYYY'),
            'to' => $range->to->isoFormat('D MMM YYYY'),
        ])">
        @include('analytics::livewire.dashboard.partials.filters')
    </x-ui.page-header>

    <livewire:analytics-funnels-content :period="$period" :subject="$subject" :key="'funnels-'.$period.'-'.$subject" />

</div>
