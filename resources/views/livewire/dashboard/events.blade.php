<div class="space-y-8">

    <x-ui.page-header
        :title="__('Événements & conversions')"
        :description="__('du :from au :to', [
            'from' => $range->from->isoFormat('D MMM YYYY'),
            'to' => $range->to->isoFormat('D MMM YYYY'),
        ])">
        @include('analytics::livewire.dashboard.partials.filters')
    </x-ui.page-header>

    <livewire:analytics-events-content :period="$period" :subject="$subject" :key="'events-content-'.$period.'-'.$subject" />

</div>
