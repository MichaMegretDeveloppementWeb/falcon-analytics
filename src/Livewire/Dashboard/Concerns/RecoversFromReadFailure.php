<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Concerns;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Renders a dashboard page while degrading gracefully: any failure while reading
 * the data is logged on the analytics channel and replaced by an inline error
 * state (inside the host layout), never a raw 500. The view builder runs outside
 * the guard so a genuine rendering bug is not masked as a read failure.
 */
trait RecoversFromReadFailure
{
    abstract protected function layoutName(): string;

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

            return view('analytics::livewire.dashboard.partials.read-error')
                ->layout($this->layoutName(), ['title' => __('Analytics')]);
        }

        return $view($assembled);
    }
}
