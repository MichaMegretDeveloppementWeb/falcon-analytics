<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers\Marketing;

use Illuminate\Contracts\View\View;

final readonly class CampaignsController
{
    public function __invoke(): View
    {
        return view('analytics::marketing.campaigns', [
            'analyticsTitle' => __('Campagnes').' · '.__('Marketing'),
        ]);
    }
}
