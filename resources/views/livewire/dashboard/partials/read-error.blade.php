<div>
    <div class="rounded-xl border border-base bg-surface px-5 py-12 text-center">
        <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-red-50 dark:bg-red-500/10">
            <x-ui.icon name="exclamation-triangle" class="h-5 w-5 text-red-600 dark:text-red-400" />
        </div>
        <p class="mt-3 text-[13px] font-medium text-primary">{{ __('Données indisponibles') }}</p>
        <p class="mt-1 text-[12px] text-secondary">{{ __('Les données n\'ont pas pu être chargées. Réessayez dans un instant.') }}</p>
    </div>
</div>
