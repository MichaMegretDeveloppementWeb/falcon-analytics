@php
    use Falcon\Analytics\Support\DeviceLabel;
    use Illuminate\Support\Str;

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
            <span>{{ __('ID') }}{{ "\u{00A0}" }}: {{ Str::limit($visitor->uuid, 24, '…') }}</span>
        </div>
    </div>

    {{-- Key figures --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
        <x-ui.stat-card :label="__('Sessions')" :value="(string) $visitor->session_count" icon="rectangle-stack" />
        <x-ui.stat-card :label="__('Pages vues')" :value="(string) $totalPageviews" icon="document-text" />
        <x-ui.stat-card :label="__('Première visite')" :value="$visitor->first_seen_at->translatedFormat('d M Y')" icon="flag" />
        <x-ui.stat-card :label="__('Dernière activité')" :value="$visitor->last_seen_at->diffForHumans()" icon="clock" />
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
                        <x-ui.table.row onclick="if (!event.target.closest('a')) window.location='{{ $sessionUrl }}'" class="cursor-pointer">
                            <x-ui.table.cell :first="true" variant="primary" class="whitespace-nowrap">
                                <a href="{{ $sessionUrl }}" class="cursor-pointer hover:underline">{{ $s->started_at->translatedFormat('d M Y, H:i') }}</a>
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
        @endif
    </div>
</div>
