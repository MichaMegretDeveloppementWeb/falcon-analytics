<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Enums\Authorization\Ability;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Support\AbilityDefaults;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

final class UndefinedAbilitiesFollowTheOneAboveTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_signed_in_account_may_do_everything_while_the_host_writes_nothing(): void
    {
        $this->actingAs(TestAdmin::create([]), 'admin');

        $this->assertSame([], $this->refused());
    }

    public function test_a_guest_may_do_nothing_while_the_host_writes_nothing(): void
    {
        $this->assertSame(Ability::cases(), $this->refused());
    }

    public function test_a_rule_on_the_root_closes_every_ability(): void
    {
        $this->actingAs(TestAdmin::create([]), 'admin');

        Gate::define(Ability::Analytics, fn (TestAdmin $admin): bool => false);

        $this->assertSame(Ability::cases(), $this->refused());
    }

    public function test_a_rule_in_the_middle_closes_its_branch_and_leaves_its_siblings(): void
    {
        $this->actingAs(TestAdmin::create([]), 'admin');

        Gate::define(Ability::Marketing, fn (TestAdmin $admin): bool => false);

        $this->assertSame(
            [
                Ability::Marketing, Ability::MarketingDashboard,
                Ability::Campaigns, Ability::CampaignsEdit, Ability::CampaignsDelete,
                Ability::Ads, Ability::AdsEdit, Ability::AdsDelete,
            ],
            $this->refused(),
        );
    }

    public function test_a_rule_lower_down_replaces_the_one_above(): void
    {
        $this->actingAs(TestAdmin::create([]), 'admin');

        Gate::define(Ability::Audience, fn (TestAdmin $admin): bool => false);
        Gate::define(Ability::Realtime, fn (TestAdmin $admin): bool => true);

        $this->assertTrue(Gate::allows(Ability::Realtime));
        $this->assertFalse(Gate::allows(Ability::Overview));
        $this->assertTrue(Gate::allows(Ability::Campaigns));
    }

    public function test_the_object_reaches_a_rule_written_higher_up(): void
    {
        $this->actingAs(TestAdmin::create([]), 'admin');

        Gate::define(
            Ability::CampaignsEdit,
            fn (TestAdmin $admin, ?Campaign $campaign = null): bool => $campaign?->name === 'Printemps',
        );

        $this->assertTrue(Gate::allows(Ability::CampaignsDelete, new Campaign(['name' => 'Printemps'])));
        $this->assertFalse(Gate::allows(Ability::CampaignsDelete, new Campaign(['name' => 'Automne'])));
        $this->assertFalse(Gate::allows(Ability::CampaignsDelete));
    }

    public function test_a_rule_that_lets_one_account_through_everything_wins_over_every_rule(): void
    {
        $this->actingAs(TestAdmin::create([]), 'admin');

        Gate::define(Ability::Analytics, fn (TestAdmin $admin): bool => false);
        Gate::before(fn (TestAdmin $admin): bool => true);

        $this->assertSame([], $this->refused());
    }

    public function test_one_line_on_the_root_opens_everything_to_a_guest(): void
    {
        Gate::define(Ability::Analytics, fn (?Authenticatable $account = null): bool => true);

        $this->assertSame([], $this->refused());
    }

    public function test_a_rule_the_host_wrote_first_is_never_replaced_by_a_default(): void
    {
        $this->actingAs(TestAdmin::create([]), 'admin');

        $defaults = $this->app->make(AbilityDefaults::class);

        Gate::define(Ability::Visitors, fn (TestAdmin $admin): bool => false);
        $defaults->fill();

        $this->assertFalse(Gate::allows(Ability::Visitors));
        $this->assertFalse($defaults->answeredByDefault(Ability::Visitors));
        $this->assertTrue($defaults->answeredByDefault(Ability::Sessions));
    }

    /** @return list<Ability> */
    private function refused(): array
    {
        return array_values(array_filter(Ability::cases(), fn (Ability $ability): bool => Gate::denies($ability)));
    }
}
