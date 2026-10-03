<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers\Marketing;

use Falcon\Analytics\Http\Controllers\Concerns\ReadsTheRowItShows;
use Falcon\Analytics\Models\Ad;
use Illuminate\Contracts\View\View;

/**
 * Read once the ability has answered, for the title it names · « Publicité »
 * when the database does not give it back.
 *
 * @internal
 */
final readonly class AdDetailController
{
    use ReadsTheRowItShows;

    public function __invoke(int $ad): View
    {
        $read = $this->rowOrNothing(fn (): Ad => Ad::query()->select(['id', 'name'])->findOrFail($ad), $ad);

        return view('analytics::admin.marketing.ad-detail', [
            'adId' => $ad,
            'analyticsTitle' => ($read->name ?? __('Publicité')).' · '.__('Marketing'),
        ]);
    }
}
