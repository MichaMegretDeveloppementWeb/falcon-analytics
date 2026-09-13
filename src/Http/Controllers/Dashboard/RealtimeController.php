<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;

/** @internal */
final readonly class RealtimeController
{
    public function __invoke(): View
    {
        return view('analytics::admin.dashboard.realtime', [
            'analyticsTitle' => __('Temps réel').' · '.__('Analytics'),
        ]);
    }
}
