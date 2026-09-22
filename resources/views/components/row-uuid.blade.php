@props(['uuid' => ''])

{{-- A visitor's identifier in a list line · its first characters, and the whole of it handed to the list's copy behaviour. --}}
<span class="an:inline-flex an:items-center an:gap-1"><span class="an:font-mono">{{ substr((string) $uuid, 0, 8) }}</span><button
    type="button"
    x-on:click="copy(@js((string) $uuid))"
    title="{{ __('Copier l\'identifiant') }}"
    aria-label="{{ __('Copier l\'identifiant') }}"
    class="an-row-link__above an:inline-flex an:shrink-0 an:cursor-pointer an:items-center an:justify-center an:rounded-lg an:p-1 an:text-muted an:transition-colors an:hover:bg-elevated an:hover:text-secondary an:max-sm:-m-2.75 an:max-sm:min-h-11 an:max-sm:min-w-11"
><span x-show="copied !== @js((string) $uuid)"><x-ui::icon name="clipboard" class="an:h-3.5 an:w-3.5" /></span><span x-show="copied === @js((string) $uuid)" x-cloak class="an:text-emerald-500"><x-ui::icon name="check" class="an:h-3.5 an:w-3.5" stroke-width="2.5" /></span></button></span>
