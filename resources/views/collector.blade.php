{{--
    What `@analyticsCollector` lays on a public page: the collector, and what it
    needs to know.

    **Nothing at all when tracking is cut**, or when the context is excluded —
    not even the file's download. That is the best way not to measure.

    **The file is declared to the kit**, like any file of a package. The kit
    builds its versioned address and lays it · in the head of an ordinary public
    page, whose layout writes no mark, and by `@falconScripts` when the
    directive sits below `@falconStyles`.

    Order does not come into it: the tag carries `defer`, so the collector runs
    after the document is parsed, and the configuration below is read before it
    whatever place the directive holds in the layout.
--}}
@php
    $analyticsConfiguration = \Falcon\Analytics\View\Collector::configuration();
@endphp

@if ($analyticsConfiguration !== null)
    <x-ui::assets package="analytics" :files="['analytics.js']" />

    <script>window.__falconAnalytics={!! $analyticsConfiguration !!};</script>
@endif
