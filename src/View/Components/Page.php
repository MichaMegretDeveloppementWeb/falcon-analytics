<?php

declare(strict_types=1);

namespace Falcon\Analytics\View\Components;

use Falcon\Ui\View\Components\Page as BasePage;

/**
 * A screen of the package: it chooses a layout, and lays down the context.
 *
 * The kit draws a button *for* someone. It only knows who while the element is
 * being drawn, from a stack this component opens before the slot and closes
 * after the wrapper. Without it the kit draws unattributed, the package's own
 * stylesheet has nothing to aim at, and a skin of its own would never be found.
 *
 * The mechanism is the base's. What is written here is the package's name, the
 * view wrapping the slot, and which layout answers for the area.
 */
final class Page extends BasePage
{
    /** The layout drawing this screen: the host's when it names one. */
    public string $layout;

    /**
     * The area is required, where the base accepts none.
     *
     * A screen that forgot to say it would draw without one, and the only
     * symptom would be a `data-ui-area` missing from elements nobody inspects.
     * Required, it raises at the tag.
     *
     * **The title is a constructor argument and not an attribute**, and that is
     * not a matter of taste: Blade escapes the attributes of a class component
     * as it lays them down, because an attribute is meant to end up inside a
     * tag. A title travelling that way reached the layout already escaped and
     * came out of `{{ }}` escaped twice — `Vue d&amp;#039;ensemble` in the
     * browser tab. Named here, it is data, and it arrives whole.
     */
    public function __construct(string $area, public ?string $title = null)
    {
        parent::__construct($area);

        // A blank value counts as absent, the same reading as
        // AnalyticsServiceProvider::configured(): a host that emptied the key
        // wants the package shell, not a layout named ''.
        $named = config("analytics.{$area}.layout");

        $this->layout = is_string($named) && $named !== ''
            ? $named
            : "analytics::layouts.{$area}";
    }

    protected function package(): string
    {
        return 'analytics';
    }

    protected function wrapper(): string
    {
        return 'analytics::components.page';
    }
}
