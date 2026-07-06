@props(['uuid' => '', 'length' => 20])

{{-- Truncated visitor uuid with an inline copy button. --}}
<span class="inline-flex items-center gap-1">{{ __('ID') }}{{ "\u{00A0}" }}: <span class="font-mono text-[12px]">{{ \Illuminate\Support\Str::limit((string) $uuid, $length, '…') }}</span><x-analytics::copy-button :value="$uuid" /></span>
