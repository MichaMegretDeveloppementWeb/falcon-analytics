@props(['uuid' => '', 'length' => 20])

{{-- Truncated visitor uuid with an inline copy button. --}}
<span class="an:inline-flex an:items-center an:gap-1">{{ __('ID') }}{{ "\u{00A0}" }}: <span class="an:font-mono an:text-[12px]">{{ \Illuminate\Support\Str::limit((string) $uuid, $length, '…') }}</span><x-analytics::copy-button :value="$uuid" /></span>
