{{--
    What the package needs on a page, declared in one line.

    The page and the root call it; a page that will open a block after a click
    can call it itself to preload, the declaration being deduplicated.

    **The package names itself and its files, nothing else**: no stack name, no
    configuration key, no deduplication identifier. The kit holds those, and it
    writes in two places — the reserved stack that `@falconStyles` and
    `@falconScripts` render, and the request's state, because the framework
    empties its stacks as soon as the topmost view has finished.

    One file only here: the package has no administration script. Its charts are
    Alpine written in the views, and Chart.js comes from the kit.
--}}
<x-ui::assets package="analytics" :files="['analytics.css']" />
