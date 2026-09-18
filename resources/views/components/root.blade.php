{{--
    What wraps a reactive view.

    The element is rendered, not only the slot: the package keeps one single
    hold for the day one of its sub-interfaces has to pin a font or a colour
    that is inherited from nowhere. Adding it later would cost going through the
    same thirty-four views a second time.

    Attributes are passed through: a view whose root carried placement classes
    moves them here rather than having them sit one level down.

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
