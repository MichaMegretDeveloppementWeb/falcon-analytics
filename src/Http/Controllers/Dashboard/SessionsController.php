<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;

final readonly class SessionsController
{
    public function __invoke(): View
    {
        return view('analytics::dashboard.sessions', [
            'analyticsTitle' => __('Sessions').' · '.__('Analytics'),
        ]);
    }
}
