<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Concerns;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Deferred-widget counterpart to RecoversFromReadFailure. The pages were reduced to
 * shells and their heavy reads moved into #[Lazy] widgets, so the widgets need the
 * same net: any failure while reading the data is logged on the analytics channel
 * and replaced by a compact inline error state, never a raw 500 that leaves the
 * skeleton hanging. The view builder runs outside the guard so a genuine rendering
 * bug is not masked as a read failure.
 */
trait GuardsWidgetRead
{
    /**
     * @param  callable(): array<string, mixed>  $data
     * @param  callable(array<string, mixed>): View  $view
     */
    protected function guardedWidget(callable $data, callable $view): View
    {
        try {
            $assembled = $data();
        } catch (Throwable $e) {
            Log::channel(config('analytics.log_channel'))->error('Analytics widget read failed.', [
                'component' => static::class,
                'exception' => $e,
            ]);

            return view('analytics::livewire.dashboard.partials.widget-error');
        }

        return $view($assembled);
    }
}
