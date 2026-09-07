<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Carbon\CarbonImmutable;
use Falcon\Analytics\Actions\IngestEventsAction;
use Falcon\Analytics\DTOs\IncomingBatch;
use Falcon\Analytics\DTOs\IncomingEvent;
use Falcon\Analytics\DTOs\RequestSnapshot;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class IngestEventsActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function snapshot(): RequestSnapshot
    {
        return new RequestSnapshot(
            ip: '85.4.12.66',
            userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:128.0) Gecko/20100101 Firefox/128.0',
            host: 'vantadrive.ch',
        );
    }

    private function incoming(
        EventType $type,
        CarbonImmutable $at,
        ?string $name = null,
        string $url = 'https://vantadrive.ch/',
    ): IncomingEvent {
        return new IncomingEvent(type: $type, occurredAt: $at, name: $name, url: $url);
    }

    public function test_it_persists_a_visitor_session_and_events_from_a_batch(): void
    {
        $now = CarbonImmutable::parse('2026-07-01 10:00:00');
        CarbonImmutable::setTestNow($now);

        app(IngestEventsAction::class)->execute('u-1', ['type' => 'client', 'id' => 7], $this->snapshot(), new IncomingBatch(events: [
            $this->incoming(EventType::Pageview, $now->subSeconds(3)),
            $this->incoming(EventType::Click, $now->subSecond(), 'listing.contact_click'),
        ]));

        $visitor = Visitor::firstOrFail();

        $this->assertSame('u-1', $visitor->uuid);
        $this->assertSame(1, $visitor->session_count);
        $this->assertSame(7, $visitor->subject_id);

        $session = Session::firstOrFail();

        $this->assertSame($visitor->id, $session->visitor_id);
        $this->assertSame(1, $session->pageview_count);
        $this->assertSame(2, $session->event_count);
        $this->assertSame('Firefox', $session->browser, 'device-detector tourne au démarrage de session');

        $this->assertSame(7, $session->subject_id);

        // Les instants reconstruits tombent une seconde environ avant le
        // `started_at` posé par le serveur ; `last_activity_at` est ramené en
        // avant pour ne jamais précéder le début.
        $this->assertSame('2026-07-01 10:00:00', $session->last_activity_at->toDateTimeString());

        $this->assertSame(2, Event::count());
    }

    public function test_it_reuses_the_open_session_within_the_timeout_and_accumulates_counters(): void
    {
        $now = CarbonImmutable::parse('2026-07-01 10:00:00');
        CarbonImmutable::setTestNow($now);
        $action = app(IngestEventsAction::class);

        $action->execute('u-1', null, $this->snapshot(), new IncomingBatch(events: [$this->incoming(EventType::Pageview, $now)]));

        CarbonImmutable::setTestNow($now->addMinutes(2));
        $action->execute('u-1', null, $this->snapshot(), new IncomingBatch(events: [$this->incoming(EventType::Click, CarbonImmutable::now(), 'x')]));

        $this->assertSame(1, Session::count());
        $this->assertSame(1, Visitor::firstOrFail()->session_count);

        $session = Session::firstOrFail();

        $this->assertSame(1, $session->pageview_count);
        $this->assertSame(2, $session->event_count);
    }

    public function test_it_starts_a_new_session_after_the_timeout(): void
    {
        $now = CarbonImmutable::parse('2026-07-01 10:00:00');
        CarbonImmutable::setTestNow($now);
        $action = app(IngestEventsAction::class);

        $action->execute('u-1', null, $this->snapshot(), new IncomingBatch(events: [$this->incoming(EventType::Pageview, $now)]));

        CarbonImmutable::setTestNow($now->addMinutes(10));
        $action->execute('u-1', null, $this->snapshot(), new IncomingBatch(events: [$this->incoming(EventType::Pageview, CarbonImmutable::now())]));

        $this->assertSame(2, Session::count());
        $this->assertSame(2, Visitor::firstOrFail()->session_count);

        // La même adresse compte quand même dans la session neuve · le garde
        // anti-rechargement repart d'une dernière adresse nulle après un délai.
        $this->assertSame(1, Session::orderByDesc('id')->first()->pageview_count);
    }

    public function test_it_collapses_a_reload_of_the_same_page_within_a_session(): void
    {
        $now = CarbonImmutable::parse('2026-07-01 10:00:00');
        CarbonImmutable::setTestNow($now);
        $action = app(IngestEventsAction::class);

        $action->execute('u-1', null, $this->snapshot(), new IncomingBatch(events: [
            $this->incoming(EventType::Pageview, $now, url: 'https://vantadrive.ch/a'),
        ]));

        CarbonImmutable::setTestNow($now->addMinute());
        $action->execute('u-1', null, $this->snapshot(), new IncomingBatch(events: [
            $this->incoming(EventType::Pageview, CarbonImmutable::now(), url: 'https://vantadrive.ch/a'),
        ]));

        $session = Session::firstOrFail();

        $this->assertSame(1, Session::count());
        $this->assertSame(1, $session->pageview_count, 'un rechargement n’est pas une nouvelle vue');
        $this->assertSame('https://vantadrive.ch/a', $session->last_pageview_url);
    }

    public function test_it_counts_real_navigations_including_returning_to_a_page(): void
    {
        $now = CarbonImmutable::parse('2026-07-01 10:00:00');
        CarbonImmutable::setTestNow($now);

        // A vers B puis retour a A fait trois.
        app(IngestEventsAction::class)->execute('u-1', null, $this->snapshot(), new IncomingBatch(events: [
            $this->incoming(EventType::Pageview, $now->subSeconds(3), url: 'https://vantadrive.ch/a'),
            $this->incoming(EventType::Pageview, $now->subSeconds(2), url: 'https://vantadrive.ch/b'),
            $this->incoming(EventType::Pageview, $now->subSecond(), url: 'https://vantadrive.ch/a'),
        ]));

        $this->assertSame(3, Session::firstOrFail()->pageview_count);
        $this->assertSame(3, Event::count());
    }

    public function test_it_bumps_activity_for_a_heartbeat_without_storing_or_counting_it(): void
    {
        $now = CarbonImmutable::parse('2026-07-01 10:00:00');
        CarbonImmutable::setTestNow($now);
        $action = app(IngestEventsAction::class);

        $action->execute('u-1', null, $this->snapshot(), new IncomingBatch(events: [$this->incoming(EventType::Pageview, $now)]));

        CarbonImmutable::setTestNow($now->addMinutes(1));
        $action->execute('u-1', null, $this->snapshot(), new IncomingBatch(events: [$this->incoming(EventType::Heartbeat, CarbonImmutable::now())]));

        $this->assertSame(1, Event::count());

        $session = Session::firstOrFail();

        $this->assertSame(1, $session->event_count);
        $this->assertSame('2026-07-01 10:01:00', $session->last_activity_at->toDateTimeString());
    }
}
