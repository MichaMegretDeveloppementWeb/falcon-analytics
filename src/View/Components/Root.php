<?php

declare(strict_types=1);

namespace Falcon\Analytics\View\Components;

use Falcon\Ui\View\Components\Root as BaseRoot;

/**
 * The context a reactive view carries for itself.
 *
 * A reactive view is recomputed on its own after a click, and the page that
 * first drew it is not replayed. Were the context laid down by the page alone,
 * it would be there on opening and gone on the next interaction: the appearance
 * would change after a click, with no error anywhere.
 *
 * The area is written here, never inherited from the page, hence the required
 * argument: inheriting would make the first render and the recomputation
 * differ.
 *
 * @internal
 */
final class Root extends BaseRoot
{
    public function __construct(string $area)
    {
        parent::__construct($area);
    }

    protected function package(): string
    {
        return 'analytics';
    }

    /** @return view-string */
    protected function wrapper(): string
    {
        return 'analytics::components.root';
    }
}
