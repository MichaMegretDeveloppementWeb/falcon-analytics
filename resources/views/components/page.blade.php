{{--
    What wraps a screen · its layout, and nothing more.

    The slot is computed before this view, so before the layout's head. That is
    what puts an asset declaration made while the screen was rendering into the
    stack by the time the layout meets `@falconStyles`.

    `$layout` and `$title` come from the class · the host's layout when it names
    one, the package's otherwise.
--}}
<x-analytics::assets />

<x-dynamic-component :component="$layout" :title="$title">
    {{ $slot }}
</x-dynamic-component>
