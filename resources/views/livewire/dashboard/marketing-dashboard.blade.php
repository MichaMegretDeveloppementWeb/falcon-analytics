<div class="space-y-8">

    <x-ui.page-header
        :title="__('Tableau de bord')"
        :description="__('du :from au :to', [
            'from' => $range->from->isoFormat('D MMM YYYY'),
            'to' => $range->to->isoFormat('D MMM YYYY'),
        ])">
        @include('analytics::livewire.dashboard.partials.filters')
    </x-ui.page-header>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-ui.stat-card :label="__('Sessions issues de pubs')" :value="number_format($sessionsFromAds, 0, ',', ' ')" icon="cursor-arrow-rays" />
        <x-ui.stat-card :label="__('Visiteurs issus de pubs')" :value="number_format($visitorsFromAds, 0, ',', ' ')" icon="users" />
        <x-ui.stat-card :label="__('Campagnes')" :value="number_format($campaignCount, 0, ',', ' ')" icon="megaphone" />
        <x-ui.stat-card :label="__('Pubs')" :value="number_format($adCount, 0, ',', ' ')" icon="rectangle-stack" />
    </div>

    <div>
        <x-ui.section-header :title="__('Performance des campagnes')" :description="__('Sessions et conversions attribuées à chaque campagne.')" class="mb-4" />
        <x-ui.empty-state
            icon="chart-bar"
            :title="__('Métriques de performance en cours de branchement')"
            :description="__('Le trafic et les conversions par campagne et par pub arrivent ici, sur la période sélectionnée.')" />
    </div>

</div>
