<x-analytics::root area="admin" class="an:rounded-xl an:border an:border-base an:bg-surface an:px-5 an:py-8 an:text-center">
    <div class="an:mx-auto an:flex an:h-9 an:w-9 an:items-center an:justify-center an:rounded-full an:bg-red-50 an:dark:bg-red-500/10">
        <x-ui::icon name="exclamation-triangle" class="an:h-4 an:w-4 an:text-red-600 an:dark:text-red-400" />
    </div>
    <p class="an:mt-2 an:text-[12px] an:font-medium an:text-primary">{{ __('Données indisponibles') }}</p>
    <p class="an:mt-0.5 an:text-[11px] an:text-secondary">{{ __('Ce bloc n\'a pas pu être chargé. Réessayez dans un instant.') }}</p>
</x-analytics::root>
