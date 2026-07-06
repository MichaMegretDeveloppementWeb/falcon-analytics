<?php

declare(strict_types=1);

namespace Falcon\Analytics\Livewire\Dashboard\Concerns;

/**
 * Resolves the layout the dashboard pages render into, honouring the host's
 * configurable shell and falling back to the package's own standalone layout.
 */
trait ResolvesDashboardLayout
{
    protected function layoutName(string $module = 'dashboard'): string
    {
        $layout = config("analytics.{$module}.layout");

        return is_string($layout) && $layout !== '' ? $layout : 'analytics::layouts.dashboard';
    }
}
