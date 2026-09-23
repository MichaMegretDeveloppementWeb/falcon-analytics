<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\AnalyticsServiceProvider;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The addresses move, the names do not.
 *
 * Where the screens hang belongs to the host: it mounts them wherever its own
 * administration lives. But the host also *writes names down* — in a menu, in a
 * redirect, in a rule that lets someone through — and a name that followed the
 * configuration could not be written down anywhere.
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
        $this->assertTrue(Route::has($name), "The route {$name} is not registered.");
        $this->assertSame($address, route($name, absolute: false));
    }

    /**
     * The detail screens take a parameter, and a host's own lists link to them.
     * The parameter name is part of the promise · a caller writes
     * `route($name, ['visitor' => …])`, so renaming `{visitor}` breaks it.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function detailScreens(): array
    {
        return [
            'a visitor' => ['analytics.admin.visitors.show', 'visitor', '/admin/analytics/visitors/7'],
            'a session' => ['analytics.admin.sessions.show', 'session', '/admin/analytics/sessions/7'],
            'a campaign' => ['analytics.admin.marketing.campaigns.show', 'campaign', '/admin/marketing/campaigns/7'],
            'an ad' => ['analytics.admin.marketing.ads.show', 'ad', '/admin/marketing/ads/7'],
        ];
    }

    #[DataProvider('detailScreens')]
    public function test_it_names_every_detail_screen_and_its_parameter(string $name, string $parameter, string $address): void
    {
        $this->assertTrue(Route::has($name), "The route {$name} is not registered.");
        $this->assertSame($address, route($name, [$parameter => 7], absolute: false));
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
     * The provider, which mounts the routes, is registered again from a moved
     * configuration, and the collection is read directly since `route()` keeps a
     * name's first registration. Each name then carries the boot address and the new one.
     */
    public function test_it_keeps_every_name_when_the_host_moves_the_addresses(): void
    {
        config([
            'analytics.admin.route_prefix' => 'panneau/mesures',
            'analytics.admin.marketing.route_prefix' => 'panneau/pubs',
            'analytics.endpoint' => 'collecte',
        ]);

        $this->app->register(AnalyticsServiceProvider::class, force: true);

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

        $this->assertSame([], $strays, 'These routes name no area.');
    }
}
