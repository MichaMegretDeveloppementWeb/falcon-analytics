{{-- Delegated, viewport-clamped tooltip. Any element carrying a data-tooltip
     attribute reveals its full value on hover, positioned so it never overflows
     the screen. One host per page (event delegation on window); x-show only, so
     it is safe inside re-rendering Livewire components. --}}
<div
    x-data="{
        text: '', show: false, x: 0, y: 0,
        move(e) {
            const el = e.target.closest ? e.target.closest('[data-tooltip]') : null;
            if (! el || ! el.getAttribute('data-tooltip')) { this.show = false; return; }
            this.text = el.getAttribute('data-tooltip');
            this.show = true;
            this.$nextTick(() => {
                const tip = this.$refs.tip;
                if (! tip) { return; }
                const r = el.getBoundingClientRect();
                this.x = Math.max(8, Math.min(r.left, window.innerWidth - tip.offsetWidth - 8));
                let y = r.top - tip.offsetHeight - 8;
                this.y = y < 8 ? r.bottom + 8 : y;
            });
        },
    }"
    @mouseover.window="move($event)"
    @scroll.window.passive="show = false"
>
    <div
        x-ref="tip"
        x-show="show"
        style="display: none;"
        :style="`left: ${x}px; top: ${y}px`"
        class="an:pointer-events-none an:fixed an:z-[70] an:max-w-md an:break-all an:rounded-lg an:bg-gray-900 an:px-2.5 an:py-1.5 an:text-[11px] an:font-medium an:text-white an:shadow-lg an:dark:bg-gray-100 an:dark:text-gray-900"
        x-text="text"
    ></div>
</div>
