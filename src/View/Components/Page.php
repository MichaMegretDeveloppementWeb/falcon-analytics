<?php

declare(strict_types=1);

namespace Falcon\Analytics\View\Components;

use Falcon\Ui\View\Components\Page as BasePage;

/**
 * A screen of the package: it chooses a layout, and lays down the context.
 *
 * The kit draws a button *for* someone. It only knows who while the element is
 * being drawn, from a stack the base opens before the slot and closes after the
 * wrapper. Without it the kit draws unattributed, the package's own stylesheet
 * has nothing to aim at, and a skin of its own would never be found.
 *
 * The mechanism is the base's, and so is the layout it resolves. What is
 * written here is the package's name and the view wrapping the slot — two
 * things, and nothing else.
 *
 * @internal
 */
final class Page extends BasePage
{
    protected function package(): string
    {
        return 'analytics';
    }

    /** @return view-string */
    protected function wrapper(): string
    {
        return 'analytics::components.page';
    }
}
