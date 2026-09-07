<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;

final readonly class RealtimeController
{
    public function __invoke(): View
    {
        return view('analytics::dashboard.realtime', [
            'analyticsTitle' => __('Temps réel').' · '.__('Analytics'),
        ]);
    }
}
