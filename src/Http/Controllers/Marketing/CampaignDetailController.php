<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers\Marketing;

use Falcon\Analytics\Models\Campaign;
use Illuminate\Contracts\View\View;

/**
 * The title names the campaign, so it is composed here rather than inside the
 * component: the browser tab is decided before the screen renders.
 */
final readonly class CampaignDetailController
{
    public function __invoke(Campaign $campaign): View
    {
        return view('analytics::marketing.campaign-detail', [
            'campaign' => $campaign,
            'analyticsTitle' => $campaign->name.' · '.__('Marketing'),
        ]);
    }
}
