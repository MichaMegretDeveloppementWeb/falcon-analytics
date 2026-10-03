<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Closure;
use Falcon\Analytics\Enums\Authorization\Ability;
use Falcon\Analytics\Livewire\Admin\AdDetailPage;
use Falcon\Analytics\Livewire\Admin\CampaignDetailPage;
use Falcon\Analytics\Livewire\Admin\SessionDetailPage;
use Falcon\Analytics\Livewire\Admin\VisitorDetailPage;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A fiche reads its row once the account may open it, says « introuvable » when
 * the row is not there, and draws its screen with its banner when the database
 * did not give the row back.
 */
final class ARecordThatIsGoneTest extends TestCase
{
    use RefreshDatabase;

    private const string SENTENCE = 'Cette page n’existe pas ou plus.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(TestAdmin::create([]), 'admin');
    }

    /**
     * Each fiche · its route, its ability, its table, a row of it, its address
     * without the identifier, the tab it falls back on, and its space.
     *
     * @return array<string, array{string, Ability, string, Closure(): int, string, string, string}>
     */
    public static function fiches(): array
    {
        return [
            'a visitor' => ['analytics.admin.visitors.show', Ability::Visitors, 'falcon_analytics_visitors', static fn (): int => Visitor::factory()->create()->id, '/admin/analytics/visitors/', 'Visiteur · Audience', 'Audience'],
            'a session' => ['analytics.admin.sessions.show', Ability::Sessions, 'falcon_analytics_sessions', static fn (): int => Session::factory()->create()->id, '/admin/analytics/sessions/', 'Session · Audience', 'Audience'],
            'a campaign' => ['analytics.admin.marketing.campaigns.show', Ability::Campaigns, 'falcon_analytics_campaigns', static fn (): int => Campaign::factory()->create()->id, '/admin/marketing/campaigns/', 'Campagne · Marketing', 'Marketing'],
            'an ad' => ['analytics.admin.marketing.ads.show', Ability::Ads, 'falcon_analytics_ads', static fn (): int => Ad::factory()->create()->id, '/admin/marketing/ads/', 'Publicité · Marketing', 'Marketing'],
        ];
    }

    /** @param Closure(): int $row */
    #[DataProvider('fiches')]
    public function test_a_row_that_is_not_there_answers_with_the_page(string $route, Ability $ability, string $table, Closure $row, string $path, string $tab, string $space): void
    {
        $this->get(route($route, 999999))
            ->assertNotFound()
            ->assertSee(self::SENTENCE)
            ->assertSee('<title>Page introuvable · '.$space, false);
    }

    /**
     * The ability is asked before the row is read · an account that may not
     * open the screen cannot tell an identifier that exists from one that does
     * not.
     *
     * @param  Closure(): int  $row
     */
    #[DataProvider('fiches')]
    public function test_an_account_refused_the_screen_learns_nothing_of_which_rows_exist(string $route, Ability $ability, string $table, Closure $row, string $path, string $tab, string $space): void
    {
        $existing = $row();

        Gate::define($ability, fn (TestAdmin $admin): bool => false);

        $this->get(route($route, $existing))->assertForbidden();
        $this->get(route($route, 999999))->assertForbidden();
    }

    /** @param Closure(): int $row */
    #[DataProvider('fiches')]
    public function test_an_identifier_that_is_no_number_answers_with_the_page(string $route, Ability $ability, string $table, Closure $row, string $path, string $tab, string $space): void
    {
        $this->get($path.'abc')->assertNotFound()->assertSee(self::SENTENCE);
        $this->get($path.str_repeat('9', 20))->assertNotFound()->assertSee(self::SENTENCE);
    }

    /** @param Closure(): int $row */
    #[DataProvider('fiches')]
    public function test_a_row_the_database_did_not_give_back_still_draws_the_screen(string $route, Ability $ability, string $table, Closure $row, string $path, string $tab, string $space): void
    {
        View::addLocation(__DIR__.'/../Fixtures/views');
        config()->set('analytics.layouts.admin', 'host-shell');

        $existing = $row();

        $this->withoutTable($table, function () use ($route, $existing, $tab): void {
            $this->get(route($route, $existing))
                ->assertOk()
                ->assertSee('<title>'.$tab.'</title>', false)
                ->assertSee('Impossible de charger')
                ->assertSee('chrome fourni par le gabarit');
        });
    }

    /**
     * Each fiche's component · the key it is mounted with, its route, its table,
     * and a row of it.
     *
     * @return array<string, array{class-string, string, string, string, Closure(): int}>
     */
    public static function screens(): array
    {
        return [
            'a visitor' => [VisitorDetailPage::class, 'visitorId', 'analytics.admin.visitors.show', 'falcon_analytics_visitors', static fn (): int => Visitor::factory()->create()->id],
            'a session' => [SessionDetailPage::class, 'sessionId', 'analytics.admin.sessions.show', 'falcon_analytics_sessions', static fn (): int => Session::factory()->create()->id],
            'a campaign' => [CampaignDetailPage::class, 'campaignId', 'analytics.admin.marketing.campaigns.show', 'falcon_analytics_campaigns', static fn (): int => Campaign::factory()->create()->id],
            'an ad' => [AdDetailPage::class, 'adId', 'analytics.admin.marketing.ads.show', 'falcon_analytics_ads', static fn (): int => Ad::factory()->create()->id],
        ];
    }

    /**
     * A row gone while the fiche was open sends back to its address, which
     * answers « introuvable » · a « Réessayer » on a row that is gone could
     * never succeed.
     *
     * @param  class-string  $component
     * @param  Closure(): int  $row
     */
    #[DataProvider('screens')]
    public function test_a_row_gone_while_the_fiche_was_open_sends_back_to_its_address(string $component, string $key, string $route, string $table, Closure $row): void
    {
        $id = $row();
        $screen = Livewire::test($component, [$key => $id]);

        DB::table($table)->where('id', $id)->delete();

        $screen->call('$refresh')->assertRedirect(route($route, $id));
    }

    public function test_forgetting_a_visitor_already_gone_says_so_and_stays(): void
    {
        $visitor = Visitor::factory()->create();
        $screen = Livewire::test(VisitorDetailPage::class, ['visitorId' => $visitor->id]);

        $visitor->delete();

        $screen->call('forget')
            ->assertDispatched('ui-toast', type: 'danger', title: 'Ce visiteur est introuvable. Actualisez la page.')
            ->assertNoRedirect();
    }

    public function test_forgetting_a_visitor_the_database_did_not_give_back_says_so_and_stays(): void
    {
        $visitor = Visitor::factory()->create();
        $screen = Livewire::test(VisitorDetailPage::class, ['visitorId' => $visitor->id]);

        $this->withoutTable('falcon_analytics_visitors', function () use ($screen): void {
            $screen->call('forget')
                ->assertDispatched('ui-toast', type: 'danger', title: 'Impossible de charger ce visiteur. Réessayez.')
                ->assertNoRedirect();
        });
    }

    public function test_deleting_a_campaign_already_gone_says_so_and_stays(): void
    {
        $campaign = Campaign::factory()->create();
        $screen = Livewire::test(CampaignDetailPage::class, ['campaignId' => $campaign->id]);

        $campaign->delete();

        $screen->call('deleteCampaignConfirmed')
            ->assertDispatched('ui-toast', type: 'danger', title: 'Cette campagne est introuvable. Actualisez la page.')
            ->assertReturned(static fn (mixed $deleted): bool => $deleted === false)
            ->assertNoRedirect();
    }

    public function test_deleting_a_campaign_the_database_did_not_give_back_says_so_and_stays(): void
    {
        $campaign = Campaign::factory()->create();
        $screen = Livewire::test(CampaignDetailPage::class, ['campaignId' => $campaign->id]);

        $this->withoutTable('falcon_analytics_campaigns', function () use ($screen): void {
            $screen->call('deleteCampaignConfirmed')
                ->assertDispatched('ui-toast', type: 'danger', title: 'Impossible de charger cette campagne. Réessayez.')
                ->assertReturned(static fn (mixed $deleted): bool => $deleted === false)
                ->assertNoRedirect();
        });
    }

    /** A folded profile holds no data of its own: its address leads to the canonical one. */
    public function test_a_merged_visitor_leads_to_the_one_it_was_folded_into(): void
    {
        $canonical = Visitor::factory()->create();
        $folded = Visitor::factory()->create(['merged_into_id' => $canonical->id]);

        $this->get(route('analytics.admin.visitors.show', $folded->id))
            ->assertRedirect(route('analytics.admin.visitors.show', $canonical->id));
    }
}
