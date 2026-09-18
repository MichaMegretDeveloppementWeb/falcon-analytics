{{--
    What wraps a screen · its layout, and the package's hold on what it draws.

    The slot is computed before this view, so before the layout's head. That is
    what puts an asset declaration made while the screen was rendering into the
    stack by the time the layout meets `@falconStyles`.

    `$layout`, `$title` and `$area` come from the base in the kit · the host's
    layout when it names one under `analytics.layouts.{area}`, the package's
    shell otherwise.

    **The element is rendered, and not only the slot.** `.an-root` is what a
    rule of the package's own aims at — `.an-root .an-something` — and without
    it a page has nothing to hang one on. It costs one element and it is the
    only hold a screen has.
--}}
<x-analytics::assets />

<x-dynamic-component :component="$layout" :title="$title">
    <div {{ $attributes->merge(['data-an-area' => $area])->class(['an-root']) }}>
        {{ $slot }}
    </div>
</x-dynamic-component>
