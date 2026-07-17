@php
    use Falcon\Analytics\Support\DeviceLabel;

    $routeName = config('analytics.dashboard.route_name', 'analytics');

    $visitorPrimary = $subjectName
        ?? ($visitor->subject_type ? $subjectLabel.' #'.$visitor->subject_id : __('Visiteur #:id', ['id' => $visitor->id]));
    $isReturning = $visitor->session_count > 1;

    $formatSeconds = function (int $seconds): string {
        $minutes = intdiv($seconds, 60);
        $rest = $seconds % 60;

        if ($minutes > 0) {
            return $rest > 0 ? "{$minutes}\u{00A0}min\u{00A0}{$rest}\u{00A0}s" : "{$minutes}\u{00A0}min";
        }

        return "{$seconds}\u{00A0}s";
    };

    $palette = ['#1684ea', '#4b9bf0', '#7cb8f2', '#a5cdf7', '#bcdcfa', '#d1d5db'];
    $deviceTotal = array_sum($devices);
    $sourceTotal = array_sum($sources);
@endphp

<div class="space-y-6">

    <div>
        <a href="{{ route($routeName.'.visitors') }}" class="inline-flex cursor-pointer items-center gap-x-1 text-[12px] font-medium text-secondary transition-colors hover:text-primary">
            <x-ui.icon name="arrow-left" class="h-3.5 w-3.5" />
            {{ __('Retour aux visiteurs') }}
        </a>
    </div>

    {{-- Header --}}
    <div class="min-w-0">
        <h1 class="text-2xl font-semibold tracking-tight text-primary">{{ $visitorPrimary }}</h1>
        <div class="mt-1.5 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-sm text-secondary">
            <span>{{ $visitor->subject_type ? $subjectLabel : __('Visiteur anonyme') }}</span>
            @if ($isReturning)
                <x-ui.badge color="blue">{{ __('Récurrent') }}</x-ui.badge>
            @endif
            <span class="text-muted">·</span>
            <x-analytics::visitor-id :uuid="$visitor->uuid" />
            <span class="text-muted">·</span>
            <span>{{ __('Première visite le :date', ['date' => $visitor->first_seen_at->translatedFormat('d M Y')]) }}</span>
        </div>
    </div>

    {{-- Engagement figures --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
        <x-ui.stat-card :label="__('Sessions')" :value="(string) $visitor->session_count" icon="rectangle-stack" />
        <x-ui.stat-card :label="__('Pages vues')" :value="(string) $totalPageviews" icon="document-text" />
        <x-ui.stat-card :label="__('Durée moy.')" :value="$formatSeconds($avgSeconds)" icon="clock" />
        <x-ui.stat-card :label="__('Pages / session')" :value="number_format($pagesPerSession, 1, ',', ' ')" icon="chart-bar" />
    </div>

    {{-- Comportement : appareils + acquisition --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-ui.card>
            <x-ui.section-header :title="__('Appareils')" class="mb-4" />
            @if ($deviceTotal > 0)
                <div class="flex items-center gap-5">
                    <x-analytics::donut
                        :labels="collect($devices)->keys()->map(fn ($d) => DeviceLabel::for($d))->all()"
                        :values="array_values($devices)"
                        :colors="array_slice($palette, 0, count($devices))"
                        :total="(string) $deviceTotal"
                        :caption="__('sessions')" />
                    <div class="flex-1 space-y-2.5">
                        @foreach ($devices as $device => $c)
                            <div class="flex items-center justify-between gap-2">
                                <span class="flex items-center gap-2 text-[13px] text-secondary"><span class="h-2 w-2 rounded-full" style="background:{{ $palette[$loop->index] ?? '#d1d5db' }}"></span>{{ DeviceLabel::for($device) }}</span>
                                <span class="text-[13px]"><span class="font-semibold text-primary">{{ ((int) round($c / $deviceTotal * 100))."\u{00A0}%" }}</span> <span class="text-muted">{{ $c }}</span></span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <x-ui.empty-state icon="device-phone-mobile" :title="__('Aucune donnée')" />
            @endif
        </x-ui.card>

        <x-ui.card>
            <x-ui.section-header :title="__('Acquisition')" class="mb-4" />
            @if ($sourceTotal > 0)
                <div class="space-y-3">
                    @foreach ($sources as $source => $c)
                        @php $pct = (int) round($c / $sourceTotal * 100); @endphp
                        <div>
                            <div class="mb-1 flex items-center justify-between text-[13px]">
                                <span class="text-secondary">@if ($source === 'direct'){{ __('Directe') }}@else<x-analytics::source :value="$source" />@endif</span>
                                <span><span class="font-semibold text-primary">{{ $pct."\u{00A0}%" }}</span> <span class="text-muted">{{ $c }}</span></span>
                            </div>
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-elevated">
                                <div class="h-full rounded-full bg-[#1684ea]" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <x-ui.empty-state icon="globe-alt" :title="__('Aucune donnée')" />
            @endif
        </x-ui.card>
    </div>

    {{-- Sessions --}}
    <div>
        <x-ui.section-header :title="__('Sessions')" class="mb-4" />

        @if ($sessions->isEmpty())
            <div class="rounded-xl border border-base bg-surface px-5 py-10 text-center text-[13px] text-secondary">
                {{ __('Aucune session pour ce visiteur.') }}
            </div>
        @else
            <x-ui.table>
                <x-ui.table.head>
                    <x-ui.table.header-cell :first="true">{{ __('Session') }}</x-ui.table.header-cell>
                    <x-ui.table.header-cell>{{ __('Durée') }}</x-ui.table.header-cell>
                    <x-ui.table.header-cell>{{ __('Pages') }}</x-ui.table.header-cell>
                    <x-ui.table.header-cell>{{ __('Appareil') }}</x-ui.table.header-cell>
                    <x-ui.table.header-cell>{{ __('Source') }}</x-ui.table.header-cell>
                    <x-ui.table.header-cell :last="true">{{ __('Localité') }}</x-ui.table.header-cell>
                </x-ui.table.head>
                <x-ui.table.body>
                    @foreach ($sessions as $s)
                        @php
                            $sessionUrl = route($routeName.'.sessions.show', $s);
                            $seconds = (int) $s->started_at->diffInSeconds($s->last_activity_at);
                        @endphp
                        <x-ui.table.row wire:key="session-{{ $s->id }}" onclick="if (!event.target.closest('a')) window.location='{{ $sessionUrl }}'" class="cursor-pointer">
                            <x-ui.table.cell :first="true" variant="primary" class="whitespace-nowrap">
                                <span class="inline-flex items-center gap-x-2">
                                    <a href="{{ $sessionUrl }}" class="cursor-pointer hover:underline">{{ $s->started_at->translatedFormat('d M Y, H:i') }}</a>
                                    @if ($visitor->subject_type && $s->subject_type)
                                        <x-ui.badge color="blue">{{ __('Connecté') }}</x-ui.badge>
                                    @endif
                                </span>
                            </x-ui.table.cell>
                            <x-ui.table.cell class="whitespace-nowrap">{{ $formatSeconds($seconds) }}</x-ui.table.cell>
                            <x-ui.table.cell class="tabular-nums">{{ $s->pageview_count }}</x-ui.table.cell>
                            <x-ui.table.cell>{{ DeviceLabel::for($s->device_type) }}</x-ui.table.cell>
                            <x-ui.table.cell>
                                @if ($s->source)
                                    <x-ui.badge color="gray"><x-analytics::source :value="$s->source" /></x-ui.badge>
                                @else
                                    <span class="text-muted">{{ __('Directe') }}</span>
                                @endif
                            </x-ui.table.cell>
                            <x-ui.table.cell :last="true" class="whitespace-nowrap">
                                @if ($s->country || $s->city)
                                    <x-analytics::country :code="$s->country" :city="$s->city" />
                                @else
                                    <span class="text-muted">{{ __('Inconnu') }}</span>
                                @endif
                            </x-ui.table.cell>
                        </x-ui.table.row>
                    @endforeach
                </x-ui.table.body>
            </x-ui.table>

            <div class="mt-6"><x-ui.pagination :paginator="$sessions" mode="livewire" /></div>
        @endif
    </div>

    {{-- Zone de danger : effacement RGPD --}}
    <div class="pt-2">
        <x-ui.section-header :title="__('Zone de danger')" :danger="true" :description="__('L\'effacement des données de ce visiteur est définitif.')" class="mb-4" />

        @error('visitor-erasure-failed')
            <x-ui.alert variant="danger" class="mb-4">{{ $message }}</x-ui.alert>
        @enderror

        <div class="flex flex-col gap-3 rounded-xl border border-red-200 bg-red-50/40 px-5 py-4 dark:border-red-500/20 dark:bg-red-500/[0.06] sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[13px] font-medium text-primary">{{ __('Supprimer les données de ce visiteur') }}</p>
                <p class="mt-0.5 text-[12px] text-secondary">{{ __('Efface le visiteur, ses sessions et ses évènements. Action irréversible (droit à l\'effacement).') }}</p>
            </div>
            <x-ui.button variant="danger" class="shrink-0" @click="$dispatch('open-modal', 'forget-visitor')">
                <x-ui.icon name="trash" class="h-4 w-4" /> {{ __('Supprimer') }}
            </x-ui.button>
        </div>
    </div>

    <x-ui.modal name="forget-visitor" variant="confirm" :title="__('Supprimer ce visiteur ?')">
        {{ __('Cette action est irréversible : le visiteur, ses :count session(s) et tous leurs évènements seront définitivement supprimés.', ['count' => $visitor->session_count]) }}
        <x-slot:actions>
            <x-ui.button variant="ghost" @click="$dispatch('close-modal', 'forget-visitor')">{{ __('Annuler') }}</x-ui.button>
            <x-ui.button variant="danger" wire:click="forget" @click="$dispatch('close-modal', 'forget-visitor')">{{ __('Supprimer définitivement') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.modal>
</div>
