<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\DTOs\Dashboard\Realtime\FeedEntry;
use Falcon\Analytics\DTOs\Dashboard\Realtime\RecentVisitorRow;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Events\EventRegistry;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Services\Dashboard\RealtimeRowBuilder;
use Falcon\Analytics\Tests\Fixtures\Models\TestClient;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * The two lists of the realtime board, as their lines read · what happened,
 * with which icon, when, who, and whether they are still there.
 */
final class TheLiveBoardSaysWhatHappenedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['analytics.events_path' => __DIR__.'/../Fixtures/analytics-events.php']);
        $this->app->forgetInstance(EventRegistry::class);
        $this->travelTo(CarbonImmutable::parse('2026-07-10 12:00:00'));
    }

    public function test_each_event_says_what_happened_with_its_icon(): void
    {
        $session = $this->aSession();
        $page = $this->anEvent($session, EventType::Pageview, ['url' => 'https://exemple.test/tarifs']);
        $declared = $this->anEvent($session, EventType::Click, ['name' => 'sample.other', 'target_text' => 'Appeler']);
        $undeclared = $this->anEvent($session, EventType::Custom, ['name' => 'newsletter']);
        $undeclaredWithText = $this->anEvent($session, EventType::Click, ['name' => 'cta.missing', 'target_text' => 'Voir plus']);
        $clicked = $this->anEvent($session, EventType::Click, ['target_text' => 'Contact']);
        $bare = $this->anEvent($session, EventType::Click);

        $feed = $this->feed();

        $this->assertSame([__('Page vue'), 'document-text', 'https://exemple.test/tarifs'], $this->said($feed[$page->id]));
        $this->assertSame(['Sample other', 'cursor-arrow-rays', null], $this->said($feed[$declared->id]));
        $this->assertSame(['newsletter', 'bolt', null], $this->said($feed[$undeclared->id]));
        $this->assertSame(['Voir plus', 'cursor-arrow-rays', null], $this->said($feed[$undeclaredWithText->id]));
        $this->assertSame(['Contact', 'cursor-arrow-rays', null], $this->said($feed[$clicked->id]));
        $this->assertSame([__('Clic'), 'cursor-arrow-rays', null], $this->said($feed[$bare->id]));
    }

    public function test_a_conversion_is_marked_and_carries_the_check(): void
    {
        $session = $this->aSession();
        $lead = $this->anEvent($session, EventType::Custom, ['name' => 'sample.action']);
        $other = $this->anEvent($session, EventType::Custom, ['name' => 'sample.other']);

        $feed = $this->feed();

        $this->assertTrue($feed[$lead->id]->isConversion);
        $this->assertSame(['Sample action', 'check-circle', null], $this->said($feed[$lead->id]));
        $this->assertFalse($feed[$other->id]->isConversion);
    }

    public function test_a_moment_of_today_reads_as_its_time_and_an_older_one_carries_its_day(): void
    {
        $today = $this->aSession(['last_activity_at' => now()->subMinute()]);
        $yesterday = $this->aSession(['started_at' => now()->subHours(13), 'last_activity_at' => CarbonImmutable::parse('2026-07-09 23:10:00')]);
        $event = $this->anEvent($today, EventType::Pageview, ['occurred_at' => now()->subMinutes(3)]);

        $visitors = $this->recentVisitors();

        $this->assertSame('11:59', $visitors[$today->id]->lastSeen);
        $this->assertSame('9 juil., 23:10', $visitors[$yesterday->id]->lastSeen);
        $this->assertSame('11:57', $this->feed()[$event->id]->occurredAt);
    }

    public function test_a_visitor_is_online_only_within_the_online_window(): void
    {
        $here = $this->aSession(['last_activity_at' => now()->subSeconds(30)]);
        $gone = $this->aSession(['last_activity_at' => now()->subMinutes(5)]);

        $visitors = $this->recentVisitors();

        $this->assertTrue($visitors[$here->id]->isOnline);
        $this->assertFalse($visitors[$gone->id]->isOnline);
    }

    public function test_a_known_visitor_is_named_and_an_anonymous_one_numbered_in_both_lists(): void
    {
        $marie = TestClient::create(['first_name' => 'Marie', 'last_name' => 'Dupont']);
        $known = $this->aSession(visitor: ['subject_type' => 'client', 'subject_id' => $marie->id]);
        $anonymous = $this->aSession();
        $this->anEvent($known, EventType::Pageview);
        $this->anEvent($anonymous, EventType::Pageview);

        $visitors = $this->recentVisitors();
        $names = array_map(static fn (FeedEntry $entry): string => $entry->name, array_values($this->feed()));

        $this->assertSame('Marie Dupont', $visitors[$known->id]->name);
        $this->assertSame(__('Visiteur #:id', ['id' => $anonymous->visitor_id]), $visitors[$anonymous->id]->name);
        $this->assertEqualsCanonicalizing(['Marie Dupont', __('Visiteur #:id', ['id' => $anonymous->visitor_id])], $names);
    }

    /**
     * The board's lists, from every session and event in the database.
     *
     * @return array{recentVisitors: list<RecentVisitorRow>, feed: list<FeedEntry>}
     */
    private function board(): array
    {
        return app(RealtimeRowBuilder::class)->build(
            Session::query()->with('visitor')->orderByDesc('last_activity_at')->get(),
            Event::query()->with('session.visitor')->orderByDesc('occurred_at')->orderByDesc('id')->get(),
            CarbonImmutable::now()->subSeconds(60),
            ['sample.action'],
        );
    }

    /** @return array<int, RecentVisitorRow> */
    private function recentVisitors(): array
    {
        $rows = [];
        foreach ($this->board()['recentVisitors'] as $row) {
            $rows[$row->sessionId] = $row;
        }

        return $rows;
    }

    /** @return array<int, FeedEntry> */
    private function feed(): array
    {
        $entries = [];
        foreach ($this->board()['feed'] as $entry) {
            $entries[$entry->id] = $entry;
        }

        return $entries;
    }

    /** @return array{string, string, string|null} */
    private function said(FeedEntry $entry): array
    {
        return [$entry->action, $entry->icon, $entry->url];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $visitor
     */
    private function aSession(array $attributes = [], array $visitor = []): Session
    {
        $owner = Visitor::create([
            'uuid' => (string) Str::uuid(),
            'first_seen_at' => now()->subDay(),
            'last_seen_at' => now(),
            'session_count' => 1,
            ...$visitor,
        ]);

        return Session::create([
            'visitor_id' => $owner->id,
            'browser_key' => $owner->uuid,
            'started_at' => now()->subMinutes(5),
            'last_activity_at' => now(),
            'is_bot' => false,
            'pageview_count' => 1,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function anEvent(Session $session, EventType $type, array $attributes = []): Event
    {
        return Event::create([
            'session_id' => $session->id,
            'visitor_id' => $session->visitor_id,
            'occurred_at' => now()->subMinute(),
            'type' => $type,
            ...$attributes,
        ]);
    }
}
