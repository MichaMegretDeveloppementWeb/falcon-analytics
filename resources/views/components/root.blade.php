{{--
    What wraps a reactive view.

    The element is rendered, not only the slot: `.an-root` is the one hold the
    package's own rules have on a reactive view, as on a screen.

    Attributes are passed through, so a view's placement classes sit on this
    element and not one level down.

    The asset declaration goes here as well: a reactive block can be drawn
    inside a host's page that is not a screen of the package, and it then has to
    bring its stylesheet itself.

    **It is INSIDE the element, never before it.** This component leaves
    whitespace behind it, and Livewire reads the root tag name of its child
    components off whatever it finds at the head of the render: whitespace
    before the element and it reads something other than a tag name, then
    throws on the next refresh.
--}}
<div {{ $attributes->merge(['class' => 'an-root']) }}><x-analytics::assets :area="$area" />{{ $slot }}</div>
