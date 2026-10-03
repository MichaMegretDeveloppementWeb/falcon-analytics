<?php

declare(strict_types=1);

namespace Falcon\Analytics\Http\Controllers\Dashboard;

use Falcon\Analytics\Http\Controllers\Concerns\ReadsTheRowItShows;
use Falcon\Analytics\Models\Session;
use Illuminate\Contracts\View\View;

/** @internal */
final readonly class SessionDetailController
{
    use ReadsTheRowItShows;

    public function __invoke(int $session): View
    {
        $this->rowOrNothing(fn (): Session => Session::query()->select('id')->findOrFail($session), $session);

        return view('analytics::admin.dashboard.session-detail', [
            'sessionId' => $session,
            'analyticsTitle' => __('Session').' · '.__('Audience'),
        ]);
    }
}
