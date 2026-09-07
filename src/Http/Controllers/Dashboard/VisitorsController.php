<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;

final readonly class VisitorsController
{
    public function __invoke(): View
    {
        return view('analytics::dashboard.visitors', [
            'analyticsTitle' => __('Visiteurs').' · '.__('Analytics'),
        ]);
    }
}
