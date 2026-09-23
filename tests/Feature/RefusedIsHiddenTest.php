<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Enums\Authorization\Ability;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Models\SearchConsoleConnection;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

/**
 * What the account may not do is not offered: no link to a screen it may not
 * open, no button for a gesture it may not make.
 */
final class RefusedIsHiddenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(TestAdmin::create([]), 'admin');
    }

    public function test_the_menu_offers_only_the_screens_the_account_may_open(): void
    {
        Gate::define(Ability::Marketing, fn (TestAdmin $admin): bool => false);

        $this->get(route('analytics.admin.overview'))
            ->assertSuccessful()
            ->assertSee(route('analytics.admin.realtime'), false)
            ->assertDontSee(route('analytics.admin.marketing.campaigns'), false)
            ->assertDontSee(route('analytics.admin.marketing.ads'), false);
    }

    public function test_a_campaign_row_offers_only_what_the_rule_allows_on_that_campaign(): void
    {
        $kept = Campaign::factory()->create(['name' => 'Automne']);
        $open = Campaign::factory()->create(['name' => 'Printemps']);

        Gate::define(Ability::CampaignsDelete, fn (TestAdmin $admin, ?Campaign $campaign = null): bool => $campaign?->name === 'Printemps');

        $this->get(route('analytics.admin.marketing.campaigns'))
            ->assertSuccessful()
            ->assertSee('confirmDelete('.$open->id.')', false)
            ->assertDontSee('confirmDelete('.$kept->id.')', false)
            ->assertSee('editCampaign('.$kept->id.')', false);
    }

    public function test_creating_a_campaign_is_not_offered_to_an_account_that_may_not_edit_campaigns(): void
    {
        Gate::define(Ability::CampaignsEdit, fn (TestAdmin $admin): bool => false);

        $this->get(route('analytics.admin.marketing.campaigns'))
            ->assertSuccessful()
            ->assertDontSee('editCampaign()', false);
    }

    public function test_erasing_a_visitor_is_not_offered_to_an_account_that_may_not(): void
    {
        $visitor = Visitor::factory()->create();

        $this->get(route('analytics.admin.visitors.show', $visitor))->assertSee('forget-visitor', false);

        Gate::define(Ability::VisitorsDelete, fn (TestAdmin $admin): bool => false);

        $this->get(route('analytics.admin.visitors.show', $visitor))
            ->assertSuccessful()
            ->assertDontSee('forget-visitor', false);
    }

    public function test_managing_search_console_is_not_offered_to_an_account_that_may_only_look(): void
    {
        config(['analytics.search_console.client_id' => 'client', 'analytics.search_console.client_secret' => 'secret']);
        SearchConsoleConnection::factory()->create();

        Gate::define(Ability::IntegrationsManage, fn (TestAdmin $admin): bool => false);

        $this->get(route('analytics.admin.integrations'))
            ->assertSuccessful()
            ->assertSee('sc-domain:example.com')
            ->assertDontSee('syncNow', false)
            ->assertDontSee('an-search-console-disconnect', false);
    }

    public function test_a_link_to_a_screen_the_account_may_not_open_becomes_text(): void
    {
        $session = Session::factory()->create();

        Gate::define(Ability::Visitors, fn (TestAdmin $admin): bool => false);

        $this->get(route('analytics.admin.sessions.show', $session))
            ->assertSuccessful()
            ->assertDontSee(route('analytics.admin.visitors.show', $session->visitor_id), false);
    }
}
