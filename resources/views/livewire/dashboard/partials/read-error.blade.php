<x-analytics::root area="admin">
    <div class="an:rounded-xl an:border an:border-base an:bg-surface an:px-5 an:py-12 an:text-center">
        <div class="an:mx-auto an:flex an:h-11 an:w-11 an:items-center an:justify-center an:rounded-full an:bg-red-50 an:dark:bg-red-500/10">
            <x-ui::icon name="exclamation-triangle" class="an:h-5 an:w-5 an:text-red-600 an:dark:text-red-400" />
        </div>
        <p class="an:mt-3 an:text-[13px] an:font-medium an:text-primary">{{ __('Données indisponibles') }}</p>
        <p class="an:mt-1 an:text-[12px] an:text-secondary">{{ __('Les données n\'ont pas pu être chargées. Réessayez dans un instant.') }}</p>
    </div>
</x-analytics::root>
