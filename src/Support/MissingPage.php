<?php

declare(strict_types=1);

namespace Falcon\Analytics\Support;

use Falcon\Analytics\Enums\Authorization\Ability;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * The package's page for what is not there, drawn in the layout the host names
 * for the administration area.
 *
 * It answers only a 404 raised under one of the package's screens, and never a
 * request that expects JSON or that the reactive layer sends: a host's own 404
 * page, and its own handlers, keep everything else · the collector included.
 *
 * @internal
 */
final readonly class MissingPage
{
    public function __construct(private Gate $gate) {}

    public function respond(Request $request): ?Response
    {
        if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
            return null;
        }

        $route = $request->route();
        $name = $route instanceof Route ? (string) $route->getName() : '';

        if (! str_starts_with($name, 'analytics.admin.')) {
            return null;
        }

        $space = str_starts_with($name, 'analytics.admin.marketing.') ? __('Marketing') : __('Audience');

        return response()->view('analytics::admin.missing', [
            'analyticsTitle' => __('Page introuvable').' · '.$space,
            'mayOpenTheOverview' => $this->gate->allows(Ability::Overview),
        ], 404);
    }
}
