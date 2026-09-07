<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers\Dashboard;

use Falcon\Analytics\Models\Session;
use Illuminate\Contracts\View\View;

final readonly class SessionDetailController
{
    public function __invoke(Session $session): View
    {
        return view('analytics::dashboard.session-detail', [
            'session' => $session,
            'analyticsTitle' => __('Session').' · '.__('Analytics'),
        ]);
    }
}
