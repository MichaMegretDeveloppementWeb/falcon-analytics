@props(['param' => '', 'value' => ''])

{{-- A URL-matching condition shown as a compact param = value chip. --}}
<span class="an:inline-flex an:items-center an:gap-1 an:rounded-lg an:border an:border-base an:px-2 an:py-0.5 an:text-[11px]">
    <span class="an:text-muted">{{ $param }}</span>
    <span class="an:text-muted">=</span>
    <span class="an:font-medium an:text-primary">{{ $value }}</span>
</span>
