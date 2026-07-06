<?php

declare(strict_types=1);

namespace Falcon\Analytics\Actions;

use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Illuminate\Support\Facades\DB;

/**
 * Erase a visitor and everything attached to them (sessions, events), for a GDPR
 * right-to-erasure request. Deletes explicitly inside a transaction rather than
 * relying on the FK cascade, so it behaves identically on every driver.
 */
final readonly class ForgetVisitorAction
{
    public function execute(Visitor $visitor): void
    {
        DB::transaction(function () use ($visitor): void {
            Event::query()->where('visitor_id', $visitor->id)->delete();
            Session::query()->where('visitor_id', $visitor->id)->delete();
            $visitor->delete();
        });
    }
}
