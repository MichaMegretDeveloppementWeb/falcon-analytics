<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;

/** @internal */
final readonly class VisitorsController
{
    public function __invoke(): View
    {
        return view('analytics::admin.dashboard.visitors', [
            'analyticsTitle' => __('Visiteurs').' · '.__('Analytics'),
        ]);
    }
}
