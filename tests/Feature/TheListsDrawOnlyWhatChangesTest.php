<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Livewire\Admin\AdsPage;
use Falcon\Analytics\Livewire\Admin\CampaignsPage;
use Falcon\Analytics\Livewire\Admin\RealtimePage;
use Falcon\Analytics\Livewire\Admin\SessionsPage;
use Falcon\Analytics\Livewire\Admin\VisitorsPage;
use Falcon\Analytics\Models\Ad;
use Falcon\Analytics\Models\Campaign;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as Events;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A row of a list costs no view: a full page of sessions, visitors, campaigns
 * or ads draws the views one row draws, and so does the live feed. A page and
 * not more: past it, the pagination appears, which is the page changing shape
 * and not a row costing anything.
 */
final class TheListsDrawOnlyWhatChangesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-07-10 12:00:00'));
        $this->actingAs(TestAdmin::create([]), 'admin');
    }

    /**
     * The views a render asks for, by name and count.
     *
     * @return array<string, int>
     */
    private function viewsOf(string $component): array
    {
        $drawn = [];

        Events::listen('composing:*', static function (string $event) use (&$drawn): void {
            $drawn[] = substr($event, strlen('composing: '));
        });

        Livewire::test($component);

        return array_count_values($drawn);
    }

    /**
     * A session carrying every column a row can show: a subject, a source, a
     * landing page, a place.
     */
    private function aSession(int $rank): Session
    {
        $visitor = Visitor::create([
            'uuid' => (string) Str::uuid(),
            'first_seen_at' => now()->subHour(),
            'last_seen_at' => now(),
            'session_count' => 1,
            'subject_type' => $rank % 2 === 0 ? 'client' : null,
            'subject_id' => $rank % 2 === 0 ? $rank : null,
        ]);

        $session = Session::create([
            'visitor_id' => $visitor->id,
            'browser_key' => $visitor->uuid,
            'started_at' => now()->subMinutes(2),
            'last_activity_at' => now(),
            'is_bot' => false,
            'pageview_count' => 2,
            'source' => ['google', 'social', 'direct'][$rank % 3],
            'landing_url' => 'https://exemple.test/page-'.$rank,
            'city' => 'Genève',
            'country' => 'CH',
            'device_type' => 'desktop',
        ]);

        Event::create([
            'session_id' => $session->id,
            'visitor_id' => $visitor->id,
            'occurred_at' => now()->subMinute(),
            'type' => EventType::Pageview,
            'url' => 'https://exemple.test/page-'.$rank,
        ]);

        return $session;
    }

    /** @return array<string, array{class-string}> */
    public static function listsOfSessions(): array
    {
        return [
            'les sessions' => [SessionsPage::class],
            'les visiteurs' => [VisitorsPage::class],
            'le temps réel' => [RealtimePage::class],
        ];
    }

    /** @param  class-string  $component */
    #[DataProvider('listsOfSessions')]
    public function test_a_page_of_rows_draws_the_views_one_draws(string $component): void
    {
        $this->aSession(0);
        $one = $this->viewsOf($component);

        foreach (range(1, 19) as $rank) {
            $this->aSession($rank);
        }

        $this->assertSame(20, Session::query()->count());
        $this->assertNotSame([], $one);
        $this->assertSame($one, $this->viewsOf($component));
    }

    public function test_a_page_of_campaigns_and_ads_draws_the_views_one_draws(): void
    {
        $aCampaign = static function (int $rank): void {
            $campaign = Campaign::create([
                'name' => 'Campagne '.$rank,
                'match_conditions' => [['param' => 'src', 'value' => 'meta-'.$rank], ['param' => 'utm_campaign', 'value' => 'ete']],
            ]);

            Ad::create([
                'campaign_id' => $campaign->id,
                'name' => 'Annonce '.$rank,
                'match_conditions' => [['param' => 'creative', 'value' => 'visuel-'.$rank]],
            ]);
        };

        $aCampaign(0);
        $campaigns = $this->viewsOf(CampaignsPage::class);
        $ads = $this->viewsOf(AdsPage::class);

        foreach (range(1, 19) as $rank) {
            $aCampaign($rank);
        }

        $this->assertSame(20, Ad::query()->count());
        $this->assertSame($campaigns, $this->viewsOf(CampaignsPage::class));
        $this->assertSame($ads, $this->viewsOf(AdsPage::class));
    }
}
