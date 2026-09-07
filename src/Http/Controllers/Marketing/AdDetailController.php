<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers\Marketing;

use Falcon\Analytics\Models\Ad;
use Illuminate\Contracts\View\View;

final readonly class AdDetailController
{
    public function __invoke(Ad $ad): View
    {
        return view('analytics::marketing.ad-detail', [
            'ad' => $ad,
            'analyticsTitle' => $ad->name.' · '.__('Marketing'),
        ]);
    }
}
