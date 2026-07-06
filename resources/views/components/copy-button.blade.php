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
    {{ $attributes->merge(['class' => 'inline-flex shrink-0 cursor-pointer items-center justify-center rounded-lg p-1 text-muted transition-colors hover:bg-elevated hover:text-secondary']) }}
>
    <span x-show="!copied"><x-ui.icon name="clipboard" class="h-3.5 w-3.5" /></span>
    <span x-show="copied" class="text-emerald-500"><x-ui.icon name="check" class="h-3.5 w-3.5" stroke-width="2.5" /></span>
</button>
