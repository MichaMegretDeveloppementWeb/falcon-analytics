<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Realtime\FeedEntry;
use Falcon\Analytics\DTOs\Dashboard\Session\JourneyEvent;
use Falcon\Analytics\DTOs\Dashboard\Session\JourneyStep;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Livewire\Admin\RealtimePage;
use Falcon\Analytics\Livewire\Admin\SessionDetailPage;
use Falcon\Analytics\Livewire\Admin\Widgets\OverviewContent;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * The same event reads the same on the journey of a session, on the live
 * feed and in the ranking of clicks · a declared event by its label, a plain
 * click by its text.
 */
final class AnEventReadsTheSameEverywhereTest extends TestCase
{
    use RefreshDatabase;

    private Session $session;

    protected function setUp(): void
    {
        parent::setUp();

        config(['analytics.events_path' => __DIR__.'/../Fixtures/analytics-events.php']);
        $this->app->forgetInstance(EventRegistry::class);
        $this->travelTo(CarbonImmutable::parse('2026-07-10 12:00:00'));
        $this->actingAs(TestAdmin::create([]), 'admin');

        $visitor = Visitor::create(['uuid' => (string) Str::uuid(), 'first_seen_at' => now(), 'last_seen_at' => now(), 'session_count' => 1]);
        $this->session = Session::create([
            'visitor_id' => $visitor->id, 'browser_key' => $visitor->uuid, 'started_at' => now()->subMinutes(5),
            'last_activity_at' => now(), 'is_bot' => false, 'pageview_count' => 1, 'click_count' => 2,
        ]);

        $this->event(EventType::Pageview, ['route' => 'home', 'url' => 'https://exemple.test/'], 4);
        $this->event(EventType::Click, ['name' => 'sample.action', 'target_text' => 'Réserver maintenant', 'route' => 'home'], 3);
        $this->event(EventType::Custom, ['name' => 'sample.other'], 2);
        $this->event(EventType::Click, ['target_text' => 'Menu', 'route' => 'home'], 1);
    }

    public function test_the_journey_of_a_session_names_each_event_by_the_rule(): void
    {
        $detail = Livewire::test(SessionDetailPage::class, ['session' => $this->session])->viewData('detail');

        $shown = array_merge(...array_map(
            static fn (JourneyStep $step): array => array_map(static fn (JourneyEvent $child): string => $child->label, $step->children),
            $detail->journey,
        ));

        $this->assertSame(['Sample action', 'Sample other', 'Menu'], $shown);
    }

    public function test_the_live_feed_names_each_event_by_the_rule(): void
    {
        $feed = Livewire::test(RealtimePage::class)->viewData('feed');

        $shown = array_map(static fn (FeedEntry $entry): string => $entry->action, array_values(array_filter(
            $feed,
            static fn (FeedEntry $entry): bool => $entry->url === null,
        )));

        $this->assertSame(['Menu', 'Sample other', 'Sample action'], $shown);
    }

    public function test_the_ranking_of_clicks_names_each_click_by_the_rule(): void
    {
        $clicks = Livewire::test(OverviewContent::class, ['period' => 30])->call('$refresh')->viewData('topClicks');

        $this->assertEqualsCanonicalizing(['Sample action', 'Menu'], array_column($clicks, 'label'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function event(EventType $type, array $attributes, int $minutesAgo): void
    {
        Event::create([
            'session_id' => $this->session->id,
            'visitor_id' => $this->session->visitor_id,
            'occurred_at' => now()->subMinutes($minutesAgo),
            'type' => $type,
            ...$attributes,
        ]);
    }
}
