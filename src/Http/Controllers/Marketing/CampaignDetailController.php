<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers\Marketing;

use Falcon\Analytics\Http\Controllers\Concerns\ReadsTheRowItShows;
use Falcon\Analytics\Models\Campaign;
use Illuminate\Contracts\View\View;

/**
 * The title names the campaign, so it is read here, once the ability has
 * answered: the browser tab is decided before the screen renders. When the
 * database does not give it back, the tab says « Campagne » and the screen
 * says what it could not load.
 *
 * @internal
 */
final readonly class CampaignDetailController
{
    use ReadsTheRowItShows;

    public function __invoke(int $campaign): View
    {
        $read = $this->rowOrNothing(fn (): Campaign => Campaign::query()->select(['id', 'name'])->findOrFail($campaign), $campaign);

        return view('analytics::admin.marketing.campaign-detail', [
            'campaignId' => $campaign,
            'analyticsTitle' => ($read->name ?? __('Campagne')).' · '.__('Marketing'),
        ]);
    }
}
