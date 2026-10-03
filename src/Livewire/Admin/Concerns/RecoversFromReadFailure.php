<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Admin\Concerns;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Throwable;

/**
 * Renders a dashboard screen while degrading gracefully: any failure while
 * reading the data is logged on the analytics channel and replaced by an inline
 * error state, never a raw 500. The view builder runs outside the guard so a
 * genuine rendering bug is not masked as a read failure.
 *
 * The error state carries no layout of its own: the screen is mounted by a
 * controller and a thin view, and this renders inside them, so a failed read
 * costs the panel, not the sidebar, the header and the page title with it.
 *
 * A fiche whose row is gone while it was open sends back to its address, which
 * answers « introuvable » · a « Réessayer » on a row that is gone could never
 * succeed.
 *
 * @internal
 *
 * @phpstan-require-extends Component
 */
trait RecoversFromReadFailure
{
    /**
     * @param  callable(): array<string, mixed>  $data
     * @param  callable(array<string, mixed>): View  $view
     */
    protected function guardedRender(callable $data, callable $view): View
    {
        try {
            $assembled = $data();
        } catch (ModelNotFoundException $gone) {
            $address = $this->addressOnceGone($gone);

            if ($address === null) {
                return $this->readFailed($gone);
            }

            $this->redirect($address);

            return view('analytics::livewire.dashboard.partials.gone');
        } catch (Throwable $e) {
            return $this->readFailed($e);
        }

        return $view($assembled);
    }

    /**
     * The address a fiche sends back to when the row it shows is gone, or null
     * when the screen only says what it could not load.
     *
     * @param  ModelNotFoundException<Model>  $gone
     */
    protected function addressOnceGone(ModelNotFoundException $gone): ?string
    {
        return null;
    }

    /** What the screen could not read, as its error says it: « Impossible de charger … ». */
    abstract protected function unreadableTitle(): string;

    private function readFailed(Throwable $e): View
    {
        Log::channel(config('analytics.log_channel'))->error('Analytics dashboard read failed.', [
            'component' => static::class,
            'exception' => $e,
        ]);

        return view('analytics::livewire.dashboard.partials.read-error', ['title' => $this->unreadableTitle()]);
    }
}
