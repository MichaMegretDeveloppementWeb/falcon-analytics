<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers\Marketing;

use Illuminate\Contracts\View\View;

final readonly class MarketingDashboardController
{
    public function __invoke(): View
    {
        return view('analytics::marketing.dashboard', [
            'analyticsTitle' => __('Vue d\'ensemble').' · '.__('Marketing'),
        ]);
    }
}
