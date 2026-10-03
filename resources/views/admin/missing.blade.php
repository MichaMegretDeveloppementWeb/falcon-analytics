<x-analytics::page area="admin" :title="$analyticsTitle" class="an:mx-auto an:max-w-[90em] an:px-4 an:py-6 an:sm:px-6 an:sm:py-8">
    <div class="an:rounded-xl an:border an:border-default an:bg-surface">
        <x-ui::empty-state icon="document-magnifying-glass" :title="__('Cette page n’existe pas ou plus.')">
            @if ($mayOpenTheOverview)
                <x-ui::button variant="secondary" :href="route('analytics.admin.overview')">{{ __('Revenir à la vue d\'ensemble') }}</x-ui::button>
            @endif
        </x-ui::empty-state>
    </div>
</x-analytics::page>
