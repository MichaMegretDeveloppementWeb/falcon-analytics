@props(['value' => ''])

{{-- Small copy-to-clipboard button. Alpine only (x-show, Livewire-safe), stops
     propagation so it never triggers a surrounding clickable row. Falls back to
     execCommand outside a secure context (plain HTTP), where the Clipboard API
     is unavailable. --}}
<button
    type="button"
    x-data="{
        copied: false,
        copy() {
            const text = @js((string) $value);
            const done = () => { this.copied = true; setTimeout(() => this.copied = false, 1400); };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done).catch(() => {});
                return;
            }

            const el = document.createElement('textarea');
            el.value = text;
            el.style.position = 'fixed';
            el.style.opacity = '0';
            document.body.appendChild(el);
            el.select();
            try { document.execCommand('copy'); done(); } catch (e) { /* ignore */ }
            document.body.removeChild(el);
        },
    }"
    @click.stop="copy()"
    :title="copied ? @js(__('Copié')) : @js(__('Copier l\'identifiant'))"
    {{ $attributes->merge(['class' => 'an:inline-flex an:shrink-0 an:cursor-pointer an:items-center an:justify-center an:rounded-lg an:p-1 an:text-muted an:transition-colors an:hover:bg-elevated an:hover:text-secondary']) }}
>
    <span x-show="!copied"><x-ui::icon name="clipboard" class="an:h-3.5 an:w-3.5" /></span>
    <span x-show="copied" class="an:text-emerald-500"><x-ui::icon name="check" class="an:h-3.5 an:w-3.5" stroke-width="2.5" /></span>
</button>
