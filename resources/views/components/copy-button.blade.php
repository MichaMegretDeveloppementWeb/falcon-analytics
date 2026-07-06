@props(['value' => ''])

{{-- Small copy-to-clipboard button. Alpine only (x-show, Livewire-safe), stops
     propagation so it never triggers a surrounding clickable row. --}}
<button
    type="button"
    x-data="{ copied: false }"
    @click.stop="navigator.clipboard.writeText(@js((string) $value)); copied = true; setTimeout(() => copied = false, 1400)"
    :title="copied ? @js(__('Copié')) : @js(__('Copier l\'identifiant'))"
    {{ $attributes->merge(['class' => 'inline-flex shrink-0 cursor-pointer items-center justify-center rounded-lg p-1 text-muted transition-colors hover:bg-elevated hover:text-secondary']) }}
>
    <span x-show="!copied"><x-ui.icon name="clipboard" class="h-3.5 w-3.5" /></span>
    <span x-show="copied" class="text-emerald-500"><x-ui.icon name="check" class="h-3.5 w-3.5" stroke-width="2.5" /></span>
</button>
