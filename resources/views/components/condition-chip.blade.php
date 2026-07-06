@props(['param' => '', 'value' => ''])

{{-- A URL-matching condition shown as a compact param = value chip. --}}
<span class="inline-flex items-center gap-1 rounded-lg border border-base px-2 py-0.5 text-[11px]">
    <span class="text-muted">{{ $param }}</span>
    <span class="text-muted">=</span>
    <span class="font-medium text-primary">{{ $value }}</span>
</span>
