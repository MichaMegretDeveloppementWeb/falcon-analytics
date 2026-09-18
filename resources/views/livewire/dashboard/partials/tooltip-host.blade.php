{{-- Delegated, viewport-clamped tooltip. Any element carrying a data-an-tooltip
     attribute reveals its full value on hover, positioned so it never overflows
     the screen. One host per page (event delegation on window); x-show only, so
     it is safe inside re-rendering Livewire components. --}}
<div
    x-data="anTooltipHost"
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
