<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Closure;
use Falcon\Analytics\Enums\Authorization\Ability;
use Falcon\Analytics\Livewire\Admin\AdDetailPage;
use Falcon\Analytics\Livewire\Admin\AdsPage;
use Falcon\Analytics\Livewire\Admin\CampaignDetailPage;
use Falcon\Analytics\Livewire\Admin\CampaignsPage;
use Falcon\Analytics\Livewire\Admin\EventsPage;
use Falcon\Analytics\Livewire\Admin\FunnelsPage;
use Falcon\Analytics\Livewire\Admin\IntegrationsPage;
use Falcon\Analytics\Livewire\Admin\MarketingDashboardPage;
use Falcon\Analytics\Livewire\Admin\OverviewPage;
use Falcon\Analytics\Livewire\Admin\RealtimePage;
use Falcon\Analytics\Livewire\Admin\SessionDetailPage;
use Falcon\Analytics\Livewire\Admin\SessionsPage;
use Falcon\Analytics\Livewire\Admin\VisitorDetailPage;
use Falcon\Analytics\Livewire\Admin\VisitorsPage;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;

final class EveryScreenAsksItsAbilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every named route of the administration, and the ability its address asks.
     *
     * @return array<string, array{0: string, 1: Ability, 2: Closure(): array<string, int>}>
     */
    public static function screens(): array
    {
        $none = static fn (): array => [];

        return [
            'overview' => ['analytics.admin.overview', Ability::Overview, $none],
            'realtime' => ['analytics.admin.realtime', Ability::Realtime, $none],
            'visitors' => ['analytics.admin.visitors', Ability::Visitors, $none],
            'visitor' => ['analytics.admin.visitors.show', Ability::Visitors, static fn (): array => ['visitor' => Visitor::factory()->create()->id]],
            'events' => ['analytics.admin.events', Ability::Events, $none],
            'funnels' => ['analytics.admin.funnels', Ability::Funnels, $none],
            'sessions' => ['analytics.admin.sessions', Ability::Sessions, $none],
            'session' => ['analytics.admin.sessions.show', Ability::Sessions, static fn (): array => ['session' => Session::factory()->create()->id]],
            'integrations' => ['analytics.admin.integrations', Ability::Integrations, $none],
            'connect' => ['analytics.admin.integrations.search-console.connect', Ability::IntegrationsManage, $none],
            'callback' => ['analytics.admin.integrations.search-console.callback', Ability::IntegrationsManage, $none],
            'marketing' => ['analytics.admin.marketing.dashboard', Ability::MarketingDashboard, $none],
            'campaigns' => ['analytics.admin.marketing.campaigns', Ability::Campaigns, $none],
            'campaign' => ['analytics.admin.marketing.campaigns.show', Ability::Campaigns, static fn (): array => ['campaign' => Campaign::factory()->create()->id]],
            'ads' => ['analytics.admin.marketing.ads', Ability::Ads, $none],
            'ad' => ['analytics.admin.marketing.ads.show', Ability::Ads, static fn (): array => ['ad' => Ad::factory()->create()->id]],
        ];
    }

    public function test_every_route_of_the_administration_is_listed_here_with_its_ability(): void
    {
        $listed = [];

        foreach (self::screens() as [$name, $ability]) {
            $listed[$name] = 'can:'.$ability->value;
        }

        $mounted = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();

            if (Str::startsWith($name, 'analytics.admin.')) {
                $mounted[$name] = $this->abilityOf($route);
            }
        }

        ksort($listed);
        ksort($mounted);

        $this->assertSame($listed, $mounted, 'A screen without its ability opens for anyone who passes the door.');
    }

    /** @param Closure(): array<string, int> $parameters */
    #[DataProvider('screens')]
    public function test_the_screen_answers_forbidden_once_its_ability_is_closed(string $name, Ability $ability, Closure $parameters): void
    {
        $this->actingAs(TestAdmin::create([]), 'admin');

        Gate::define($ability, fn (TestAdmin $admin): bool => false);

        $this->get(route($name, $parameters()))->assertForbidden();
    }

    /** @param Closure(): array<string, int> $parameters */
    #[DataProvider('screens')]
    public function test_the_screen_opens_while_its_ability_is_open(string $name, Ability $ability, Closure $parameters): void
    {
        $this->actingAs(TestAdmin::create([]), 'admin');

        Gate::define(Ability::Analytics, fn (TestAdmin $admin): bool => false);
        Gate::define($ability, fn (TestAdmin $admin): bool => true);

        $status = $this->get(route($name, $parameters()))->getStatusCode();

        $this->assertNotSame(403, $status, $name.' refused although its own ability says yes.');
        $this->assertLessThan(500, $status);
    }

    /**
     * Every screen component, and the ability it asks wherever it is mounted.
     *
     * @return array<string, array{0: class-string, 1: Ability, 2: Closure(): array<string, mixed>}>
     */
    public static function components(): array
    {
        $none = static fn (): array => [];

        return [
            'overview' => [OverviewPage::class, Ability::Overview, $none],
            'realtime' => [RealtimePage::class, Ability::Realtime, $none],
            'visitors' => [VisitorsPage::class, Ability::Visitors, $none],
            'visitor' => [VisitorDetailPage::class, Ability::Visitors, static fn (): array => ['visitor' => Visitor::factory()->create()]],
            'events' => [EventsPage::class, Ability::Events, $none],
            'funnels' => [FunnelsPage::class, Ability::Funnels, $none],
            'sessions' => [SessionsPage::class, Ability::Sessions, $none],
            'session' => [SessionDetailPage::class, Ability::Sessions, static fn (): array => ['session' => Session::factory()->create()]],
            'integrations' => [IntegrationsPage::class, Ability::Integrations, $none],
            'marketing' => [MarketingDashboardPage::class, Ability::MarketingDashboard, $none],
            'campaigns' => [CampaignsPage::class, Ability::Campaigns, $none],
            'campaign' => [CampaignDetailPage::class, Ability::Campaigns, static fn (): array => ['campaign' => Campaign::factory()->create()]],
            'ads' => [AdsPage::class, Ability::Ads, $none],
            'ad' => [AdDetailPage::class, Ability::Ads, static fn (): array => ['ad' => Ad::factory()->create()]],
        ];
    }

    /**
     * @param  class-string  $component
     * @param  Closure(): array<string, mixed>  $parameters
     */
    #[DataProvider('components')]
    public function test_a_screen_mounted_elsewhere_still_asks_its_ability(string $component, Ability $ability, Closure $parameters): void
    {
        $this->actingAs(TestAdmin::create([]), 'admin');

        Gate::define($ability, fn (TestAdmin $admin): bool => false);

        Livewire::test($component, $parameters())->assertForbidden();
    }

    private function abilityOf(RouteDefinition $route): ?string
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && Str::startsWith($middleware, 'can:')) {
                return $middleware;
            }
        }

        return null;
    }
}
