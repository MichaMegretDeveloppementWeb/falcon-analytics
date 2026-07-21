@php
    use Falcon\Analytics\Models\SearchConsoleConnection;

    $routeName = config('analytics.dashboard.route_name', 'analytics');
    $connectUrl = route($routeName.'.integrations.search-console.connect');
    $callbackUrl = route($routeName.'.integrations.search-console.callback');
@endphp

<div class="space-y-8">
    <x-ui.page-header :title="__('Intégrations')" :description="__('Sources de données externes du tableau de bord')" />

    <div class="max-w-4xl">
        <x-ui.section-header
            :title="__('Google Search Console')"
            :description="__('Les vrais termes de recherche Google qui mènent au site : clics, impressions, position')"
            class="mb-4" />

        <x-ui.card>
            {{-- Identity row: what this integration is, and its current state. --}}
            <div class="flex items-center justify-between gap-4 border-b border-subtle pb-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-elevated">
                        <x-ui.icon name="magnifying-glass" class="h-5 w-5 text-secondary" />
                    </span>
                    <div>
                        <p class="text-[13px] font-semibold text-primary">{{ __('Google Search Console') }}</p>
                        <p class="text-[12px] text-secondary">{{ __('Recherche organique · accès en lecture seule') }}</p>
                    </div>
                </div>

                @if (! $configured)
                    <x-ui.badge>{{ __('Non configurée') }}</x-ui.badge>
                @elseif ($connection?->isConnected())
                    <x-ui.badge color="emerald">{{ __('Connectée') }}</x-ui.badge>
                @elseif ($connection?->status === SearchConsoleConnection::STATUS_ERROR)
                    <x-ui.badge color="red">{{ __('Erreur') }}</x-ui.badge>
                @elseif ($connection !== null)
                    <x-ui.badge color="amber">{{ __('Propriété à choisir') }}</x-ui.badge>
                @else
                    <x-ui.badge>{{ __('Non connectée') }}</x-ui.badge>
                @endif
            </div>

            @if (! $configured)
                {{-- The host has not provided OAuth credentials: explain instead of offering a dead button. --}}
                <div class="space-y-3 pt-4">
                    <p class="text-[12px] text-secondary">
                        {{ __('Renseignez un client OAuth Google (type « Application Web », API Search Console activée) dans le fichier .env, puis rechargez cette page :') }}
                    </p>
                    <div class="rounded-lg bg-elevated px-4 py-3 font-mono text-[12px] leading-6 text-primary">
                        ANALYTICS_GSC_CLIENT_ID<br>ANALYTICS_GSC_CLIENT_SECRET
                    </div>
                    <p class="text-[12px] text-muted">
                        {{ __('URI de redirection à enregistrer sur le client OAuth :') }}
                        <span class="font-mono break-all text-secondary">{{ $callbackUrl }}</span>
                    </p>
                </div>
            @elseif ($connection === null)
                <div class="flex flex-col gap-4 pt-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="max-w-md text-[12px] text-secondary">
                        {{ __('Connectez le compte Google propriétaire du site (vérifié dans Search Console). L\'autorisation est en lecture seule et révocable à tout moment.') }}
                    </p>
                    <x-ui.button class="shrink-0 whitespace-nowrap" :href="$connectUrl">
                        {{ __('Connecter Google Search Console') }}
                    </x-ui.button>
                </div>
            @elseif ($connection->status === SearchConsoleConnection::STATUS_PENDING_PROPERTY)
                <div class="space-y-4 pt-4">
                    <p class="text-[12px] text-secondary">{{ __('Compte Google connecté. Choisissez la propriété Search Console à rattacher au tableau de bord :') }}</p>

                    @if ($propertiesFailed)
                        <div class="flex items-center justify-between gap-4 rounded-lg bg-elevated px-4 py-3">
                            <p class="text-[12px] text-secondary">{{ __('La liste des propriétés n\'a pas pu être chargée.') }}</p>
                            <x-ui.button variant="secondary" size="compact" class="shrink-0 whitespace-nowrap" wire:click="reloadProperties">{{ __('Réessayer') }}</x-ui.button>
                        </div>
                    @elseif ($properties === [])
                        <div class="rounded-lg bg-elevated px-4 py-3">
                            <p class="text-[12px] text-secondary">{{ __('Aucune propriété vérifiée sur ce compte Google. Vérifiez le site dans Search Console puis réessayez.') }}</p>
                        </div>
                    @else
                        <ul class="divide-y divide-subtle rounded-lg border border-base">
                            @foreach ($properties as $property)
                                <li class="flex items-center justify-between gap-4 px-4 py-3" wire:key="prop-{{ md5($property['site_url']) }}">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <x-ui.icon name="globe-alt" class="h-4 w-4 shrink-0 text-muted" />
                                        <div class="min-w-0">
                                            <p class="truncate text-[13px] font-medium text-primary">{{ $property['site_url'] }}</p>
                                            <p class="text-[11px] text-muted">{{ $property['permission'] }}</p>
                                        </div>
                                    </div>
                                    <x-ui.button variant="secondary" size="compact" class="shrink-0 whitespace-nowrap" wire:click="selectProperty('{{ $property['site_url'] }}')">
                                        {{ __('Rattacher') }}
                                    </x-ui.button>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="flex justify-end">
                        <x-ui.button variant="ghost" size="compact" wire:click="confirmDisconnect">{{ __('Annuler la connexion') }}</x-ui.button>
                    </div>
                </div>
            @else
                <div class="space-y-4 pt-4">
                    <x-ui.description-list>
                        <x-ui.description-list.item :label="__('Propriété')">{{ $connection->property }}</x-ui.description-list.item>
                        <x-ui.description-list.item :label="__('Dernière synchronisation')">
                            {{ $connection->last_synced_at?->translatedFormat('j M Y, H:i') ?? __('Jamais') }}
                        </x-ui.description-list.item>
                    </x-ui.description-list>

                    @if ($connection->status === SearchConsoleConnection::STATUS_ERROR)
                        <div class="rounded-lg bg-red-50 px-4 py-3 dark:bg-red-500/10">
                            <p class="text-[12px] text-red-700 dark:text-red-400">
                                {{ __('L\'accès Google ne répond plus (autorisation expirée ou révoquée). Reconnectez le compte.') }}
                            </p>
                        </div>
                    @else
                        <p class="text-[12px] text-muted">
                            {{ __('Les mots-clés sont synchronisés chaque nuit et affichés sur la vue d\'ensemble (« Clics par recherches Google »).') }}
                        </p>
                    @endif

                    <div class="flex flex-wrap items-center justify-end gap-2 border-t border-subtle pt-4">
                        @if ($connection->status === SearchConsoleConnection::STATUS_ERROR)
                            <x-ui.button variant="secondary" class="whitespace-nowrap" :href="$connectUrl">
                                {{ __('Reconnecter') }}
                            </x-ui.button>
                        @else
                            {{-- Same code path as the nightly command; the initial
                                 backfill (~16 months) can take a little while. --}}
                            <x-ui.button variant="secondary" class="whitespace-nowrap" :loading="true" target="syncNow" wire:click="syncNow">
                                {{ __('Synchroniser maintenant') }}
                            </x-ui.button>
                        @endif
                        <x-ui.button variant="danger" class="whitespace-nowrap" wire:click="confirmDisconnect">{{ __('Déconnecter') }}</x-ui.button>
                    </div>
                </div>
            @endif
        </x-ui.card>
    </div>

    {{-- Modals --}}
    <div x-on:keydown.escape.window="$wire.modal !== '' && $wire.closeModal()">
        <div x-show="$wire.modal === 'disconnect'" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm dark:bg-black/60"></div>
            <div class="relative flex min-h-full items-center justify-center p-4" @click.self="$wire.closeModal()">
                <div class="w-full max-w-md rounded-xl border border-base bg-surface p-5 shadow-xl">
                    <h3 class="text-[13px] font-semibold text-primary">{{ __('Déconnecter Search Console ?') }}</h3>
                    <p class="mt-2 text-[12px] text-secondary">
                        {{ __('L\'autorisation Google sera révoquée. Les mots-clés déjà synchronisés restent affichés, mais ne seront plus mis à jour.') }}
                    </p>
                    <div class="mt-4 flex justify-end gap-2">
                        <x-ui.button type="button" variant="ghost" wire:click="closeModal">{{ __('Annuler') }}</x-ui.button>
                        <x-ui.button type="button" variant="danger" wire:click="disconnectConfirmed">{{ __('Déconnecter') }}</x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
