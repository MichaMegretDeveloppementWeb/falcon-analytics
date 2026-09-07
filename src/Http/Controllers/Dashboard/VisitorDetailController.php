<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers\Dashboard;

use Falcon\Analytics\Models\Visitor;
use Illuminate\Contracts\View\View;

/**
 * The visitor is bound here and handed to the screen as a prop.
 *
 * It used to reach the component straight from the route, back when the
 * component was the page. Resolving it one step earlier changes nothing for a
 * visitor of the site, and gives the host a view it can wrap.
 */
final readonly class VisitorDetailController
{
    public function __invoke(Visitor $visitor): View
    {
        return view('analytics::dashboard.visitor-detail', [
            'visitor' => $visitor,
            'analyticsTitle' => __('Visiteur').' · '.__('Analytics'),
        ]);
    }
}
