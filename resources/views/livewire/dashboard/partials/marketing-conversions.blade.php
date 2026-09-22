@php
    use Falcon\Analytics\Support\NumberLabel;

    $showAd = $showAd ?? true;
@endphp

{{-- Sorted list of conversion elements (objectives): what converted, how many
     times, via which ad. A funnel objective expands into its step events. --}}
<div>
    <x-ui::section-header :title="__('Conversions')" :description="__('Ce qui a converti, combien de fois' . ($showAd ? ', et via quelle pub' : '') . ', du plus au moins converti.')" class="an:mb-4" />

    @if ($conversionElements === [])
        <x-ui::empty-state icon="check-circle" :title="__('Aucun objectif défini')" :description="__('Définis des objectifs sur tes pubs pour mesurer les conversions ici.')" />
    @else
        <x-ui::card padding="false">
            <div class="an:divide-y an:divide-subtle">
                @foreach ($conversionElements as $i => $el)
                    <div wire:key="conv-{{ $i }}" @if ($el['steps']) x-data="{ open: false }" @endif class="an:px-5 an:py-3">
                        {{-- Narrow, the label is the only thing worth reading here, so
                             what competes with it steps aside: the badge repeats what
                             the icon beside it already says, the count asks for its own
                             width instead of a fixed one, and the label wraps rather
                             than being cut. --}}
                        <div class="an:flex an:items-center an:gap-2 an:sm:gap-3">
                            @if ($el['steps'])
                                <button type="button" x-on:click="open = ! open" class="an:flex an:h-4 an:w-4 an:shrink-0 an:cursor-pointer an:items-center an:justify-center" aria-label="{{ __('Détail du tunnel') }}">
                                    <x-ui::icon name="chevron-right" class="an:h-4 an:w-4 an:text-muted an:transition-transform" x-bind:class="open && 'an:rotate-90'" />
                                </button>
                            @else
                                <span class="an:w-4 an:shrink-0"></span>
                            @endif
                            <x-ui::icon :name="$el['type'] === 'funnel' ? 'funnel' : 'bolt'" class="an:h-4 an:w-4 an:shrink-0 {{ $el['type'] === 'funnel' ? 'an:text-blue-500' : 'an:text-emerald-500' }}" />
                            <span class="an:min-w-0 an:flex-1 an:text-[13px] an:font-medium an:text-primary an:sm:truncate">{{ $el['label'] }}</span>
                            <x-ui::badge :color="$el['type'] === 'funnel' ? 'blue' : 'emerald'" class="an:hidden an:sm:inline-flex">{{ $el['type'] === 'funnel' ? __('Tunnel') : __('Événement') }}</x-ui::badge>
                            @if ($showAd)
                                <a href="{{ route('analytics.admin.marketing.ads.show', $el['adId']) }}" class="an:hidden an:w-32 an:shrink-0 an:cursor-pointer an:truncate an:text-right an:text-[12px] an:text-secondary an:hover:text-primary an:hover:underline an:sm:inline">{{ $el['adName'] }}</a>
                            @endif
                            <span class="an:shrink-0 an:text-right an:text-base an:font-semibold an:text-primary an:tabular-nums an:sm:w-16">{{ NumberLabel::for($el['conversions']) }}</span>
                        </div>
                        @if ($el['steps'])
                            <div x-show="open" x-cloak class="an:ml-2 an:mt-3 an:space-y-1.5 an:border-l an:border-default an:pl-4">
                                <p class="an:text-[11px] an:font-medium an:uppercase an:tracking-wider an:text-muted">{{ __('Étapes du tunnel') }}</p>
                                @php $maxStep = collect($el['steps'])->max('count') ?: 1; @endphp
                                @foreach ($el['steps'] as $si => $step)
                                    <div class="an:flex an:items-center an:gap-3">
                                        <span class="an:w-44 an:shrink-0 an:truncate an:text-[12px] an:text-secondary">{{ $si + 1 }}. {{ $step['label'] }}</span>
                                        <div class="an:relative an:h-1.5 an:flex-1 an:overflow-hidden an:rounded-full an:bg-elevated">
                                            <div class="an:absolute an:inset-y-0 an:left-0 an:rounded-full an:bg-series-1/70" style="width: {{ max((int) round($step['count'] / $maxStep * 100), 2) }}%"></div>
                                        </div>
                                        <span class="an:w-10 an:shrink-0 an:text-right an:text-[12px] an:font-medium an:text-secondary an:tabular-nums">{{ NumberLabel::for($step['count']) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-ui::card>
    @endif
</div>
