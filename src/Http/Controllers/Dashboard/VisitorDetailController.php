<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers\Dashboard;

use Falcon\Analytics\Http\Controllers\Concerns\ReadsTheRowItShows;
use Falcon\Analytics\Models\Visitor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * The visitor is read here, once the ability has answered, and handed to the
 * screen by its identifier, so the host gets a view it can wrap.
 *
 * @internal
 */
final readonly class VisitorDetailController
{
    use ReadsTheRowItShows;

    public function __invoke(int $visitor): View|RedirectResponse
    {
        $read = $this->rowOrNothing(fn (): Visitor => Visitor::query()->select(['id', 'merged_into_id'])->findOrFail($visitor), $visitor);

        // A folded profile holds no data of its own: its address leads to the canonical profile.
        if ($read?->merged_into_id !== null) {
            return redirect()->route('analytics.admin.visitors.show', $read->merged_into_id);
        }

        return view('analytics::admin.dashboard.visitor-detail', [
            'visitorId' => $visitor,
            'analyticsTitle' => __('Visiteur').' · '.__('Audience'),
        ]);
    }
}
