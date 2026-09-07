<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;

final readonly class OverviewController
{
    public function __invoke(): View
    {
        return view('analytics::dashboard.overview', [
            'analyticsTitle' => __('Vue d\'ensemble').' · '.__('Analytics'),
        ]);
    }
}
