<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;

final readonly class EventsController
{
    public function __invoke(): View
    {
        return view('analytics::dashboard.events', [
            'analyticsTitle' => __('Événements').' · '.__('Analytics'),
        ]);
    }
}
