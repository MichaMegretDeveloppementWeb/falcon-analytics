<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The addresses move, the names do not.
 *
 * Where the screens hang belongs to the host: it mounts them wherever its own
 * administration lives, and the package has no opinion. But the host also
 * *writes names down* — in a menu, in a redirect, in a rule that lets someone
 * through — and a name that follows the configuration cannot be written down
 * anywhere.
 *
 * It did follow it until 2026-09-11. Two keys, `dashboard.route_name` and
 * `marketing.route_name`, decided what `route()` answered to, so twenty-one
 * call sites rebuilt the name from the configuration before asking for a URL,
 * and a host that renamed either broke every one of them at once — with a
 * RouteNotFoundException raised from inside a view, at the first visit and
 * never before.
 *
 * The keys are gone. What is left is this contract, and these are the tests
 * that hold it.
 */
final class TheRouteNamesDoNotMoveTest extends TestCase
{
    /**
     * Every screen of the administration, and the address it answers at with
     * the package defaults.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function administrationScreens(): array
    {
        return [
            'overview' => ['analytics.admin.overview', '/admin/analytics'],
            'realtime' => ['analytics.admin.realtime', '/admin/analytics/realtime'],
            'visitors' => ['analytics.admin.visitors', '/admin/analytics/visitors'],
            'events' => ['analytics.admin.events', '/admin/analytics/events'],
            'funnels' => ['analytics.admin.funnels', '/admin/analytics/funnels'],
            'sessions' => ['analytics.admin.sessions', '/admin/analytics/sessions'],
            'integrations' => ['analytics.admin.integrations', '/admin/analytics/integrations'],
            'search console, connect' => ['analytics.admin.integrations.search-console.connect', '/admin/analytics/integrations/search-console/connect'],
            'search console, callback' => ['analytics.admin.integrations.search-console.callback', '/admin/analytics/integrations/search-console/callback'],
            'marketing' => ['analytics.admin.marketing.dashboard', '/admin/marketing'],
            'campaigns' => ['analytics.admin.marketing.campaigns', '/admin/marketing/campaigns'],
            'ads' => ['analytics.admin.marketing.ads', '/admin/marketing/ads'],
        ];
    }

    #[DataProvider('administrationScreens')]
    public function test_it_names_every_screen_after_the_area_it_belongs_to(string $name, string $address): void
    {
        $this->assertTrue(Route::has($name), "La route {$name} n'est pas enregistrée.");
        $this->assertSame($address, route($name, absolute: false));
    }

    /**
     * The public area holds one route, and the collector is its only caller.
     * Its name carries the area too, so that nothing has to know whether it was
     * declared beside the screens or apart from them.
     */
    public function test_it_names_the_ingestion_endpoint_after_the_public_area(): void
    {
        $this->assertTrue(Route::has('analytics.web.ingest'));
        $this->assertSame('/__analytics', route('analytics.web.ingest', absolute: false));
    }

    /**
     * The whole point, in one test: three addresses moved, and not one name did.
     *
     * The registration is replayed from a moved configuration, then the routes
     * are read straight from the collection rather than through `route()` —
     * a name already taken keeps pointing at its first registration, so the
     * helper would answer with the old address and prove nothing either way.
     *
     * Each name is expected to carry *two* addresses: the one the provider
     * registered at boot, and the one this replay just added. Anything else
     * means the name followed the prefix.
     */
    public function test_it_keeps_every_name_when_the_host_moves_the_addresses(): void
    {
        config([
            'analytics.admin.route_prefix' => 'panneau/mesures',
            'analytics.admin.marketing.route_prefix' => 'panneau/pubs',
            'analytics.endpoint' => 'collecte',
        ]);

        require dirname(__DIR__, 2).'/routes/admin.php';
        require dirname(__DIR__, 2).'/routes/web.php';

        $this->assertSame(
            ['admin/analytics', 'panneau/mesures'],
            $this->addressesOf('analytics.admin.overview'),
        );

        $this->assertSame(
            ['admin/analytics/visitors/{visitor}', 'panneau/mesures/visitors/{visitor}'],
            $this->addressesOf('analytics.admin.visitors.show'),
        );

        $this->assertSame(
            ['admin/marketing/campaigns', 'panneau/pubs/campaigns'],
            $this->addressesOf('analytics.admin.marketing.campaigns'),
        );

        $this->assertSame(
            ['__analytics', 'collecte'],
            $this->addressesOf('analytics.web.ingest'),
        );
    }

    /**
     * Every address currently registered under one name, in the order they were
     * declared.
     *
     * @return list<string>
     */
    private function addressesOf(string $name): array
    {
        $addresses = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->getName() === $name) {
                $addresses[] = $route->uri();
            }
        }

        return $addresses;
    }

    /**
     * A route added without its area would answer at the wrong name and nothing
     * would say so: `route()` only complains about names that do not exist.
     */
    public function test_it_files_every_route_of_the_package_under_an_area(): void
    {
        $strays = [];

        foreach (array_keys(Route::getRoutes()->getRoutesByName()) as $name) {
            if (! str_starts_with($name, 'analytics.')) {
                continue;
            }

            if (! str_starts_with($name, 'analytics.admin.') && ! str_starts_with($name, 'analytics.web.')) {
                $strays[] = $name;
            }
        }

        $this->assertSame([], $strays, 'Ces routes ne nomment aucun espace.');
    }
}
