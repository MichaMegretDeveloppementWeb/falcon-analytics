<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin;

use Falcon\Analytics\Enums\Authorization\Ability;
use Falcon\Analytics\Livewire\Admin\Concerns\AsksTheScreenAbility;
use Falcon\Analytics\Livewire\Admin\Concerns\RecoversFromReadFailure;
use Falcon\Analytics\Models\SearchConsoleConnection;
use Falcon\Analytics\Services\SearchConsole\SearchConsoleAuth;
use Falcon\Analytics\Services\SearchConsole\SearchConsoleClient;
use Falcon\Analytics\Services\SearchConsole\SearchConsoleSynchronizer;
use Falcon\Analytics\Support\NumberLabel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Throwable;

/**
 * The integrations screen, holding the Google Search Console card:
 * connect (OAuth start), pick the property to attach, disconnect (confirmed
 * by modal, token revoked best-effort). While the host has not configured the
 * OAuth credentials the card explains what to provide instead of offering a
 * dead button. OAuth redirects land here with a flashed message replayed as a
 * toast.
 *
 * @internal
 */
final class IntegrationsPage extends Component
{
    use AsksTheScreenAbility;
    use RecoversFromReadFailure;

    /** Widened execution window for the inline on-demand sync, when allowed. */
    private const SYNC_TIME_LIMIT_SECONDS = 300;

    /** @var list<array{site_url: string, permission: string}> */
    public array $properties = [];

    public bool $propertiesFailed = false;

    public function mount(): void
    {
        $flash = session()->pull('analytics.search_console.flash');

        if (is_array($flash)) {
            $this->dispatch('ui-toast', type: (string) $flash['type'], title: (string) $flash['title']);
        }

        if (Gate::allows(Ability::IntegrationsManage)) {
            $this->loadPropertiesIfPending();
        }
    }

    public function reloadProperties(): void
    {
        $this->authorize(Ability::IntegrationsManage);

        $this->loadPropertiesIfPending();

        if ($this->propertiesFailed) {
            $this->dispatch('ui-toast', type: 'danger', title: __('La liste des propriétés n\'a pas pu être chargée. Réessayez.'));
        }
    }

    public function selectProperty(string $siteUrl): void
    {
        $this->authorize(Ability::IntegrationsManage);

        $connection = SearchConsoleConnection::current();

        if ($connection === null || ! in_array($siteUrl, array_column($this->properties, 'site_url'), true)) {
            return;
        }

        try {
            $connection->update([
                'property' => $siteUrl,
                'status' => SearchConsoleConnection::STATUS_CONNECTED,
                'last_error' => null,
            ]);
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('SearchConsole.select_property_failed', ['exception' => $e]);
            $this->dispatch('ui-toast', type: 'danger', title: __('Le rattachement de la propriété a échoué. Réessayez.'));

            return;
        }

        $this->properties = [];
        $this->dispatch('ui-toast', type: 'success', title: __('Search Console connectée.'));
    }

    /**
     * On-demand sync, same code path as the daily command. Runs inline, with no
     * worker required: the first backfill can take a while on busy sites, so
     * the button carries a loading state and the execution window is widened
     * when the host allows it.
     */
    public function syncNow(SearchConsoleSynchronizer $synchronizer): void
    {
        $this->authorize(Ability::IntegrationsManage);

        $connection = SearchConsoleConnection::current();

        if ($connection === null || ! $connection->isConnected()) {
            return;
        }

        @set_time_limit(self::SYNC_TIME_LIMIT_SECONDS);

        try {
            $count = $synchronizer->sync($connection);
        } catch (Throwable) {
            // Already flagged and logged by the synchronizer.
            $this->dispatch('ui-toast', type: 'danger', title: __('La synchronisation a échoué. Consultez l\'état de la connexion.'));

            return;
        }

        $this->dispatch('ui-toast', type: 'success', title: $count < 2
            ? __(':count ligne synchronisée depuis Search Console.', ['count' => NumberLabel::for($count)])
            : __(':count lignes synchronisées depuis Search Console.', ['count' => NumberLabel::for($count)]));
    }

    /** Whether Search Console was disconnected · the confirmation closes on a yes. */
    public function disconnectConfirmed(SearchConsoleAuth $auth): bool
    {
        $this->authorize(Ability::IntegrationsManage);

        $connection = SearchConsoleConnection::current();

        if ($connection !== null) {
            try {
                $auth->revoke($connection);
                $connection->delete();
            } catch (Throwable $e) {
                Log::channel(config('analytics.log_channel'))->error('SearchConsole.disconnect_failed', ['exception' => $e]);
                $this->dispatch('ui-toast', type: 'danger', title: __('La déconnexion a échoué. Réessayez.'));

                return false;
            }
        }

        $this->properties = [];
        $this->dispatch('ui-toast', type: 'success', title: __('Search Console déconnectée.'));

        return true;
    }

    public function render(SearchConsoleAuth $auth): View
    {
        return $this->guardedRender(
            fn (): array => [
                'configured' => $auth->configured(),
                'connection' => SearchConsoleConnection::current(),
                'mayManage' => Gate::allows(Ability::IntegrationsManage),
            ],
            fn (array $data): View => view('analytics::livewire.dashboard.integrations', $data),
        );
    }

    protected function screenAbility(): Ability
    {
        return Ability::Integrations;
    }

    /**
     * The property list only exists between the OAuth return and the attach
     * choice; a Google failure leaves an explicit retry state instead of an
     * empty list pretending the account owns no property.
     */
    private function loadPropertiesIfPending(): void
    {
        try {
            $connection = SearchConsoleConnection::current();
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('SearchConsole.list_properties_failed', ['exception' => $e]);
            $this->properties = [];
            $this->propertiesFailed = true;

            return;
        }

        if ($connection === null || $connection->status !== SearchConsoleConnection::STATUS_PENDING_PROPERTY) {
            $this->properties = [];
            $this->propertiesFailed = false;

            return;
        }

        try {
            $this->properties = app(SearchConsoleClient::class)->listProperties($connection);
            $this->propertiesFailed = false;
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('SearchConsole.list_properties_failed', ['exception' => $e]);
            $this->properties = [];
            $this->propertiesFailed = true;
        }
    }
}
