<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers\Dashboard;

use Falcon\Analytics\Models\Visitor;
use Illuminate\Contracts\View\View;

/**
 * The visitor is bound here and handed to the screen as a prop, so the host
 * gets a view it can wrap.
 *
 * @internal
 */
final readonly class VisitorDetailController
{
    public function __invoke(Visitor $visitor): View
    {
        return view('analytics::admin.dashboard.visitor-detail', [
            'visitor' => $visitor,
            'analyticsTitle' => __('Visiteur').' · '.__('Analytics'),
        ]);
    }
}
