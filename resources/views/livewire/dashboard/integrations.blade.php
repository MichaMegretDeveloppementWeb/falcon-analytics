@php
    use Falcon\Analytics\Models\SearchConsoleConnection;

    $connectUrl = route('analytics.admin.integrations.search-console.connect');
    $callbackUrl = route('analytics.admin.integrations.search-console.callback');
@endphp

<x-analytics::root area="admin" class="an:space-y-8">
    <x-ui::page-header :title="__('Intégrations')" :description="__('Sources de données externes du tableau de bord')" />

    <div class="an:max-w-4xl">
        <x-ui::section-header
            :title="__('Google Search Console')"
            :description="__('Les vrais termes de recherche Google qui mènent au site : clics, impressions, position')"
            class="an:mb-4" />

        <x-ui::card>
            {{-- Identity row --}}
            <div class="an:flex an:items-center an:justify-between an:gap-4 an:border-b an:border-subtle an:pb-4">
                <div class="an:flex an:items-center an:gap-3">
                    <span class="an:flex an:h-10 an:w-10 an:shrink-0 an:items-center an:justify-center an:rounded-lg an:bg-elevated">
                        <x-ui::icon name="magnifying-glass" class="an:h-5 an:w-5 an:text-secondary" />
                    </span>
                    <div>
                        <p class="an:text-[13px] an:font-semibold an:text-primary">{{ __('Google Search Console') }}</p>
                        <p class="an:text-[12px] an:text-secondary">{{ __('Recherche organique · accès en lecture seule') }}</p>
                    </div>
                </div>

                @if (! $configured)
                    <x-ui::badge>{{ __('Non configurée') }}</x-ui::badge>
                @elseif ($connection?->isConnected())
                    <x-ui::badge color="emerald">{{ __('Connectée') }}</x-ui::badge>
                @elseif ($connection?->status === SearchConsoleConnection::STATUS_ERROR)
                    <x-ui::badge color="red">{{ __('Erreur') }}</x-ui::badge>
                @elseif ($connection !== null)
                    <x-ui::badge color="amber">{{ __('Propriété à choisir') }}</x-ui::badge>
                @else
                    <x-ui::badge>{{ __('Non connectée') }}</x-ui::badge>
                @endif
            </div>

            @if (! $configured)
                {{-- The host has not provided OAuth credentials: explain instead of offering a dead button. --}}
                <div class="an:space-y-3 an:pt-4">
                    <p class="an:text-[12px] an:text-secondary">
                        {{ __('Renseignez un client OAuth Google (type « Application Web », API Search Console activée) dans le fichier .env, puis rechargez cette page :') }}
                    </p>
                    <div class="an:rounded-lg an:bg-elevated an:px-4 an:py-3 an:font-mono an:text-[12px] an:leading-6 an:text-primary">
                        ANALYTICS_GSC_CLIENT_ID<br>ANALYTICS_GSC_CLIENT_SECRET
                    </div>
                    <p class="an:text-[12px] an:text-muted">
                        {{ __('URI de redirection à enregistrer sur le client OAuth :') }}
                        <span class="an:font-mono an:break-all an:text-secondary">{{ $callbackUrl }}</span>
                    </p>
                </div>
            @elseif ($connection === null)
                <div class="an:flex an:flex-col an:gap-4 an:pt-4 an:sm:flex-row an:sm:items-center an:sm:justify-between">
                    <p class="an:max-w-md an:text-[12px] an:text-secondary">
                        {{ __('Connectez le compte Google propriétaire du site (vérifié dans Search Console). L\'autorisation est en lecture seule et révocable à tout moment.') }}
                    </p>
                    <x-ui::button class="an:shrink-0 an:whitespace-nowrap" :href="$connectUrl">
                        {{ __('Connecter Google Search Console') }}
                    </x-ui::button>
                </div>
            @elseif ($connection->status === SearchConsoleConnection::STATUS_PENDING_PROPERTY)
                <div class="an:space-y-4 an:pt-4">
                    <p class="an:text-[12px] an:text-secondary">{{ __('Compte Google connecté. Choisissez la propriété Search Console à rattacher au tableau de bord :') }}</p>

                    @if ($propertiesFailed)
                        <div class="an:flex an:items-center an:justify-between an:gap-4 an:rounded-lg an:bg-elevated an:px-4 an:py-3">
                            <p class="an:text-[12px] an:text-secondary">{{ __('La liste des propriétés n\'a pas pu être chargée.') }}</p>
                            <x-ui::button variant="secondary" size="compact" class="an:shrink-0 an:whitespace-nowrap" wire:click="reloadProperties">{{ __('Réessayer') }}</x-ui::button>
                        </div>
                    @elseif ($properties === [])
                        <div class="an:rounded-lg an:bg-elevated an:px-4 an:py-3">
                            <p class="an:text-[12px] an:text-secondary">{{ __('Aucune propriété vérifiée sur ce compte Google. Vérifiez le site dans Search Console puis réessayez.') }}</p>
                        </div>
                    @else
                        <ul class="an:divide-y an:divide-subtle an:rounded-lg an:border an:border-default">
                            @foreach ($properties as $property)
                                <li class="an:flex an:items-center an:justify-between an:gap-4 an:px-4 an:py-3" wire:key="prop-{{ md5($property['site_url']) }}">
                                    <div class="an:flex an:min-w-0 an:items-center an:gap-3">
                                        <x-ui::icon name="globe-alt" class="an:h-4 an:w-4 an:shrink-0 an:text-muted" />
                                        <div class="an:min-w-0">
                                            <p class="an:truncate an:text-[13px] an:font-medium an:text-primary">{{ $property['site_url'] }}</p>
                                            <p class="an:text-[11px] an:text-muted">{{ $property['permission'] }}</p>
                                        </div>
                                    </div>
                                    <x-ui::button variant="secondary" size="compact" class="an:shrink-0 an:whitespace-nowrap" wire:click="selectProperty('{{ $property['site_url'] }}')">
                                        {{ __('Rattacher') }}
                                    </x-ui::button>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="an:flex an:justify-end">
                        <x-ui::button variant="ghost" size="compact" wire:click="confirmDisconnect">{{ __('Annuler la connexion') }}</x-ui::button>
                    </div>
                </div>
            @else
                <div class="an:space-y-4 an:pt-4">
                    <x-ui::description-list>
                        <x-ui::description-list.item :label="__('Propriété')">{{ $connection->property }}</x-ui::description-list.item>
                        <x-ui::description-list.item :label="__('Dernière synchronisation')">
                            {{ $connection->last_synced_at?->translatedFormat('j M Y, H:i') ?? __('Jamais') }}
                        </x-ui::description-list.item>
                    </x-ui::description-list>

                    @if ($connection->status === SearchConsoleConnection::STATUS_ERROR)
                        <div class="an:rounded-lg an:bg-red-50 an:px-4 an:py-3 an:dark:bg-red-500/10">
                            <p class="an:text-[12px] an:text-red-700 an:dark:text-red-400">
                                {{ __('L\'accès Google ne répond plus (autorisation expirée ou révoquée). Reconnectez le compte.') }}
                            </p>
                        </div>
                    @else
                        <p class="an:text-[12px] an:text-muted">
                            {{ __('Les mots-clés sont synchronisés chaque nuit et affichés sur la vue d\'ensemble (« Clics par recherches Google »).') }}
                        </p>
                    @endif

                    <div class="an:flex an:flex-wrap an:items-center an:justify-end an:gap-2 an:border-t an:border-subtle an:pt-4">
                        @if ($connection->status === SearchConsoleConnection::STATUS_ERROR)
                            <x-ui::button variant="secondary" class="an:whitespace-nowrap" :href="$connectUrl">
                                {{ __('Reconnecter') }}
                            </x-ui::button>
                        @else
                            {{-- Same code path as the nightly command; the initial
                                 backfill (~16 months) can take a little while. --}}
                            <x-ui::button variant="secondary" class="an:whitespace-nowrap" :loading="true" target="syncNow" wire:click="syncNow">
                                {{ __('Synchroniser maintenant') }}
                            </x-ui::button>
                        @endif
                        <x-ui::button variant="danger" class="an:whitespace-nowrap" wire:click="confirmDisconnect">{{ __('Déconnecter') }}</x-ui::button>
                    </div>
                </div>
            @endif
        </x-ui::card>
    </div>

    {{-- Modals --}}
    <div x-on:keydown.escape.window="$wire.modal !== '' && $wire.closeModal()">
        <div x-show="$wire.modal === 'disconnect'" x-cloak class="an:fixed an:inset-0 an:z-50 an:overflow-y-auto">
            <div class="an:fixed an:inset-0 an:bg-gray-900/50 an:backdrop-blur-sm an:dark:bg-black/60"></div>
            <div class="an:relative an:flex an:min-h-full an:items-center an:justify-center an:p-4" @click.self="$wire.closeModal()">
                <div class="an:w-full an:max-w-md an:rounded-xl an:border an:border-default an:bg-surface an:p-5 an:shadow-xl">
                    <h3 class="an:text-[13px] an:font-semibold an:text-primary">{{ __('Déconnecter Search Console ?') }}</h3>
                    <p class="an:mt-2 an:text-[12px] an:text-secondary">
                        {{ __('L\'autorisation Google sera révoquée. Les mots-clés déjà synchronisés restent affichés, mais ne seront plus mis à jour.') }}
                    </p>
                    <div class="an:mt-4 an:flex an:justify-end an:gap-2">
                        <x-ui::button type="button" variant="ghost" wire:click="closeModal">{{ __('Annuler') }}</x-ui::button>
                        <x-ui::button type="button" variant="danger" wire:click="disconnectConfirmed">{{ __('Déconnecter') }}</x-ui::button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-analytics::root>
