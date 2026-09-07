<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Concerns;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Renders a dashboard screen while degrading gracefully: any failure while
 * reading the data is logged on the analytics channel and replaced by an inline
 * error state, never a raw 500. The view builder runs outside the guard so a
 * genuine rendering bug is not masked as a read failure.
 *
 * The error state no longer carries a layout of its own. The screen is mounted
 * by a controller and a thin view now, so this renders inside them: a failed
 * read costs the panel, not the sidebar, the header and the page title with it.
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
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Analytics dashboard read failed.', [
                'component' => static::class,
                'exception' => $e,
            ]);

            return view('analytics::livewire.dashboard.partials.read-error');
        }

        return $view($assembled);
    }
}
