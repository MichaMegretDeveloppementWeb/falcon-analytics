{{--
    What the package needs on a page, declared in one line.

    The page and the root call it; a page that will open a block after a click
    can call it itself to preload, the declaration being deduplicated.

    **The package names itself and its files, nothing else**: no stack name, no
    configuration key, no deduplication identifier. The kit holds those, and it
    writes in two places — the reserved stack that `@falconStyles` and
    `@falconScripts` render, and the request's state, because the framework
    empties its stacks as soon as the topmost view has finished.

    **The area decides the list.** `analytics-admin.js` carries the dashboard's
    Alpine components, and only an administration screen uses them.

    **And `analytics.js` stays out of the list.** It is the
    collector — the tracker a host puts on its own public pages — and
    `collector.blade.php` declares it for itself, where it is rendered. Named
    here, every screen of the dashboard would download it to count nothing.
--}}
@props(['area' => null])

<x-ui::assets
    package="analytics"
    :files="$area === 'admin' ? ['analytics.css', 'analytics-admin.js'] : ['analytics.css']"
/>
