<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard;

use Falcon\Analytics\Livewire\Dashboard\Concerns\ResolvesDashboardLayout;
use Falcon\Analytics\Models\SearchConsoleConnection;
use Falcon\Analytics\Services\SearchConsole\SearchConsoleAuth;
use Falcon\Analytics\Services\SearchConsole\SearchConsoleClient;
use Falcon\Analytics\Services\SearchConsole\SearchConsoleSynchronizer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Throwable;

/**
 * The integrations screen, currently hosting the Google Search Console card:
 * connect (OAuth start), pick the property to attach, disconnect (confirmed
 * by modal, token revoked best-effort). While the host has not configured the
 * OAuth credentials the card explains what to provide instead of offering a
 * dead button. OAuth redirects land here with a flashed message replayed as a
 * toast.
 */
final class IntegrationsPage extends Component
{
    use ResolvesDashboardLayout;

    /** @var list<array{site_url: string, permission: string}> */
    public array $properties = [];

    public bool $propertiesFailed = false;

    /** '' | disconnect */
    public string $modal = '';

    public function mount(): void
    {
        $flash = session()->pull('analytics.search_console.flash');

        if (is_array($flash)) {
            $this->dispatch('toast', type: (string) $flash['type'], title: (string) $flash['title']);
        }

        $this->loadPropertiesIfPending();
    }

    public function reloadProperties(): void
    {
        $this->loadPropertiesIfPending();

        if ($this->propertiesFailed) {
            $this->dispatch('toast', type: 'danger', title: __('La liste des propriétés n\'a pas pu être chargée. Réessayez.'));
        }
    }

    public function selectProperty(string $siteUrl): void
    {
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
            $this->dispatch('toast', type: 'danger', title: __('Le rattachement de la propriété a échoué. Réessayez.'));

            return;
        }

        $this->properties = [];
        $this->dispatch('toast', type: 'success', title: __('Search Console connectée.'));
    }

    /**
     * On-demand sync, same code path as the daily command. Runs inline (no
     * worker required anywhere, by design): the first backfill can take a
     * while on busy sites, so the button carries a loading state and the
     * execution window is widened when the host allows it.
     */
    public function syncNow(SearchConsoleSynchronizer $synchronizer): void
    {
        $connection = SearchConsoleConnection::current();

        if ($connection === null || ! $connection->isConnected()) {
            return;
        }

        @set_time_limit(300);

        try {
            $count = $synchronizer->sync($connection);
        } catch (Throwable) {
            // Already flagged and logged by the synchronizer.
            $this->dispatch('toast', type: 'danger', title: __('La synchronisation a échoué. Consultez l\'état de la connexion.'));

            return;
        }

        $this->dispatch('toast', type: 'success', title: __(':count lignes synchronisées depuis Search Console.', ['count' => number_format($count, 0, ',', ' ')]));
    }

    public function confirmDisconnect(): void
    {
        $this->modal = 'disconnect';
    }

    public function disconnectConfirmed(SearchConsoleAuth $auth): void
    {
        $connection = SearchConsoleConnection::current();

        if ($connection !== null) {
            try {
                $auth->revoke($connection);
                $connection->delete();
            } catch (Throwable $e) {
                Log::channel(config('analytics.log_channel'))->error('SearchConsole.disconnect_failed', ['exception' => $e]);
                $this->dispatch('toast', type: 'danger', title: __('La déconnexion a échoué. Réessayez.'));

                return;
            }
        }

        $this->modal = '';
        $this->properties = [];
        $this->dispatch('toast', type: 'success', title: __('Search Console déconnectée.'));
    }

    public function closeModal(): void
    {
        $this->modal = '';
    }

    public function render(SearchConsoleAuth $auth): View
    {
        return view('analytics::livewire.dashboard.integrations', [
            'configured' => $auth->configured(),
            'connection' => SearchConsoleConnection::current(),
        ])->layout($this->layoutName(), ['title' => __('Intégrations').' · '.__('Analytics')]);
    }

    /**
     * The property list only exists between the OAuth return and the attach
     * choice; a Google failure leaves an explicit retry state instead of an
     * empty list pretending the account owns no property.
     */
    private function loadPropertiesIfPending(): void
    {
        $connection = SearchConsoleConnection::current();

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
