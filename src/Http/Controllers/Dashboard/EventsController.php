<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;

/** @internal */
final readonly class EventsController
{
    public function __invoke(): View
    {
        return view('analytics::admin.dashboard.events', [
            'analyticsTitle' => __('Événements').' · '.__('Audience'),
        ]);
    }
}
