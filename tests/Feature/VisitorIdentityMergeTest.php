<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Actions\ForgetVisitorAction;
use Falcon\Analytics\Actions\IngestEventsAction;
use Falcon\Analytics\DTOs\IncomingBatch;
use Falcon\Analytics\DTOs\IncomingEvent;
use Falcon\Analytics\DTOs\RequestSnapshot;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Services\VisitorMerger;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class VisitorIdentityMergeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-07-10 12:00:00'));
    }

    /**
     * @param  array{type: string, id: int}|null  $subject
     */
    private function visitor(string $uuid, ?array $subject = null, string $firstSeen = '2026-07-01 10:00:00'): Visitor
    {
        return Visitor::create([
            'uuid' => $uuid,
            'first_seen_at' => CarbonImmutable::parse($firstSeen),
            'last_seen_at' => CarbonImmutable::parse($firstSeen),
            'session_count' => 0,
            'subject_type' => $subject['type'] ?? null,
            'subject_id' => $subject['id'] ?? null,
        ]);
    }

    /**
     * @param  array{type: string, id: int}|null  $subject
     */
    private function sessionRow(Visitor $visitor, string $startedAt, ?array $subject = null, bool $withEvent = false): Session
    {
        $session = Session::create([
            'visitor_id' => $visitor->id,
            'browser_key' => $visitor->uuid,
            'started_at' => CarbonImmutable::parse($startedAt),
            'last_activity_at' => CarbonImmutable::parse($startedAt),
            'is_bot' => false,
            'pageview_count' => 1,
            'subject_type' => $subject['type'] ?? null,
            'subject_id' => $subject['id'] ?? null,
        ]);

        $visitor->increment('session_count');

        if ($withEvent) {
            Event::create([
                'session_id' => $session->id,
                'visitor_id' => $visitor->id,
                'occurred_at' => CarbonImmutable::parse($startedAt),
                'type' => EventType::Pageview,
            ]);
        }

        return $session;
    }

    /**
     * @param  array{type: string, id: int}|null  $subject
     */
    private function ingest(string $uuid, ?array $subject): void
    {
        app(IngestEventsAction::class)->execute($uuid, $subject, new RequestSnapshot(
            ip: '85.4.12.66',
            userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            host: 'vantadrive.test',
        ), new IncomingBatch(events: [
            new IncomingEvent(type: EventType::Pageview, occurredAt: CarbonImmutable::now(), url: 'https://vantadrive.test/'),
        ]));
    }

    // ── Fusion à l'identification ────────────────────────────────────────

    public function test_it_folds_a_newly_identified_browser_into_the_person_existing_profile(): void
    {
        $canonical = $this->visitor('uuid-pc', ['type' => 'client', 'id' => 7], '2026-07-01 10:00:00');
        $this->sessionRow($canonical, '2026-07-01 10:00:00', ['type' => 'client', 'id' => 7]);

        $phone = $this->visitor('uuid-phone', null, '2026-07-05 09:00:00');
        $this->sessionRow($phone, '2026-07-05 09:00:00', null, withEvent: true);
        $this->sessionRow($phone, '2026-07-06 18:00:00');

        $this->ingest('uuid-phone', ['type' => 'client', 'id' => 7]);

        $phone->refresh();
        $canonical->refresh();

        $this->assertSame($canonical->id, $phone->merged_into_id);
        $this->assertSame(0, $phone->session_count);
        $this->assertSame(0, Session::query()->where('visitor_id', $phone->id)->count());
        $this->assertSame(0, Event::query()->where('visitor_id', $phone->id)->count());
        $this->assertSame(4, Session::query()->where('visitor_id', $canonical->id)->count());
        $this->assertSame(4, $canonical->session_count);
        $this->assertSame('2026-07-01 10:00:00', $canonical->first_seen_at->toDateTimeString());
    }

    public function test_it_keeps_routing_a_folded_uuid_to_the_canonical_profile(): void
    {
        $canonical = $this->visitor('uuid-pc', ['type' => 'client', 'id' => 7]);
        $phone = $this->visitor('uuid-phone');
        $phone->update(['merged_into_id' => $canonical->id]);

        $this->ingest('uuid-phone', null);

        $this->assertSame(1, Session::query()->where('visitor_id', $canonical->id)->count());
        $this->assertSame(0, Session::query()->where('visitor_id', $phone->id)->count());
    }

    public function test_it_keeps_two_simultaneously_browsing_devices_in_two_separate_sessions(): void
    {
        $this->ingest('uuid-pc', ['type' => 'client', 'id' => 7]);
        $this->ingest('uuid-phone', ['type' => 'client', 'id' => 7]);
        $this->ingest('uuid-pc', ['type' => 'client', 'id' => 7]);

        $canonical = Visitor::query()->where('uuid', 'uuid-pc')->firstOrFail();
        $sessions = Session::query()->where('visitor_id', $canonical->id)->orderBy('id')->get();

        $this->assertSame($canonical->id, Visitor::query()->where('uuid', 'uuid-phone')->value('merged_into_id'));
        $this->assertCount(2, $sessions);
        $this->assertSame(['uuid-pc', 'uuid-phone'], $sessions->pluck('browser_key')->sort()->values()->all());
    }

    // ── Navigateur partagé ───────────────────────────────────────────────

    public function test_it_routes_a_login_on_a_shared_browser_to_the_person_own_profile(): void
    {
        $shared = $this->visitor('uuid-shared', ['type' => 'client', 'id' => 7]);
        $own = $this->visitor('uuid-phone', ['type' => 'client', 'id' => 9]);

        $this->ingest('uuid-shared', ['type' => 'client', 'id' => 9]);

        $session = Session::query()->latest('id')->firstOrFail();

        $this->assertSame($own->id, $session->visitor_id);
        $this->assertSame('uuid-shared', $session->browser_key);
        $this->assertSame(7, $shared->refresh()->subject_id);
        $this->assertNull($shared->merged_into_id);
    }

    public function test_it_leaves_a_shared_browser_login_on_the_browser_when_the_person_has_no_profile_yet(): void
    {
        $shared = $this->visitor('uuid-shared', ['type' => 'client', 'id' => 7]);

        $this->ingest('uuid-shared', ['type' => 'client', 'id' => 9]);

        $session = Session::query()->latest('id')->firstOrFail();

        $this->assertSame($shared->id, $session->visitor_id);
        $this->assertSame(9, $session->subject_id);
        $this->assertSame(7, $shared->refresh()->subject_id);
    }

    public function test_it_relocates_stray_identified_sessions_when_the_person_gets_their_own_profile(): void
    {
        $shared = $this->visitor('uuid-shared', ['type' => 'client', 'id' => 7]);
        $stray = $this->sessionRow($shared, '2026-07-05 09:00:00', ['type' => 'client', 'id' => 9], withEvent: true);

        $this->ingest('uuid-phone', ['type' => 'client', 'id' => 9]);

        $own = Visitor::query()->where('uuid', 'uuid-phone')->firstOrFail();

        $this->assertSame($own->id, $stray->refresh()->visitor_id);
        $this->assertSame($own->id, Event::query()->where('session_id', $stray->id)->value('visitor_id'));
        $this->assertSame(2, $own->session_count);
        $this->assertSame(0, $shared->refresh()->session_count);
    }

    // ── Consolidation (migration) ────────────────────────────────────────

    public function test_it_consolidates_pre_existing_duplicate_profiles_into_the_oldest_one(): void
    {
        $oldest = $this->visitor('uuid-a', ['type' => 'client', 'id' => 7], '2026-06-01 10:00:00');
        $this->sessionRow($oldest, '2026-06-01 10:00:00', ['type' => 'client', 'id' => 7]);

        $newer = $this->visitor('uuid-b', ['type' => 'client', 'id' => 7], '2026-06-10 10:00:00');
        $this->sessionRow($newer, '2026-06-10 10:00:00', ['type' => 'client', 'id' => 7], withEvent: true);
        $this->sessionRow($newer, '2026-06-11 10:00:00');

        $foreign = $this->visitor('uuid-c', ['type' => 'lessor', 'id' => 2], '2026-06-05 10:00:00');
        $strayOnForeign = $this->sessionRow($foreign, '2026-06-05 10:00:00', ['type' => 'client', 'id' => 7]);

        app(VisitorMerger::class)->consolidateExisting();

        $oldest->refresh();

        $this->assertSame($oldest->id, $newer->refresh()->merged_into_id);
        $this->assertNull($oldest->merged_into_id);
        $this->assertSame(4, Session::query()->where('visitor_id', $oldest->id)->count());
        $this->assertSame(4, $oldest->session_count);
        $this->assertSame($oldest->id, $strayOnForeign->refresh()->visitor_id);
        $this->assertNull($foreign->refresh()->merged_into_id);
        $this->assertSame(0, $foreign->session_count);
    }

    public function test_it_widens_the_canonical_seen_window_when_merging(): void
    {
        $canonical = $this->visitor('uuid-a', ['type' => 'client', 'id' => 7], '2026-06-10 10:00:00');
        $alias = $this->visitor('uuid-b', ['type' => 'client', 'id' => 7], '2026-06-01 08:00:00');
        $alias->update(['last_seen_at' => CarbonImmutable::parse('2026-07-09 22:00:00')]);

        $merged = app(VisitorMerger::class)->execute($alias, $canonical);

        $this->assertSame('2026-06-01 08:00:00', $merged->first_seen_at->toDateTimeString());
        $this->assertSame('2026-07-09 22:00:00', $merged->last_seen_at->toDateTimeString());
    }

    // ── RGPD et navigation ───────────────────────────────────────────────

    public function test_it_erases_the_merged_aliases_together_with_the_canonical_profile(): void
    {
        $canonical = $this->visitor('uuid-a', ['type' => 'client', 'id' => 7]);
        $alias = $this->visitor('uuid-b');
        $alias->update(['merged_into_id' => $canonical->id]);

        app(ForgetVisitorAction::class)->execute($canonical);

        $this->assertSame(0, Visitor::query()->count());
    }

    public function test_it_redirects_the_detail_of_a_folded_profile_to_the_canonical_one(): void
    {
        $canonical = $this->visitor('uuid-a', ['type' => 'client', 'id' => 7]);
        $alias = $this->visitor('uuid-b');
        $alias->update(['merged_into_id' => $canonical->id]);

        $this->actingAs(TestAdmin::create([]), 'admin')
            ->get(route('analytics.visitors.show', $alias))
            ->assertRedirect(route('analytics.visitors.show', $canonical));
    }
}
