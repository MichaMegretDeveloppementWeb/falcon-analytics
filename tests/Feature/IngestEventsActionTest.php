<?php

use Carbon\CarbonImmutable;
use Falcon\Analytics\Actions\IngestEventsAction;
use Falcon\Analytics\DTOs\IncomingBatch;
use Falcon\Analytics\DTOs\IncomingEvent;
use Falcon\Analytics\DTOs\RequestSnapshot;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function actionSnapshot(): RequestSnapshot
{
    return new RequestSnapshot(
        ip: '85.4.12.66',
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:128.0) Gecko/20100101 Firefox/128.0',
        host: 'vantadrive.ch',
    );
}

function incomingEvent(EventType $type, CarbonImmutable $at, ?string $name = null, string $url = 'https://vantadrive.ch/'): IncomingEvent
{
    return new IncomingEvent(type: $type, occurredAt: $at, name: $name, url: $url);
}

it('persists a visitor, session and events from a batch', function () {
    $now = CarbonImmutable::parse('2026-07-01 10:00:00');
    CarbonImmutable::setTestNow($now);

    app(IngestEventsAction::class)->execute('u-1', ['type' => 'client', 'id' => 7], actionSnapshot(), new IncomingBatch(events: [
        incomingEvent(EventType::Pageview, $now->subSeconds(3)),
        incomingEvent(EventType::Click, $now->subSecond(), 'listing.contact_click'),
    ]));

    $visitor = Visitor::firstOrFail();
    expect($visitor->uuid)->toBe('u-1')
        ->and($visitor->session_count)->toBe(1)
        ->and($visitor->subject_id)->toBe(7);

    $session = Session::firstOrFail();
    expect($session->visitor_id)->toBe($visitor->id)
        ->and($session->pageview_count)->toBe(1)
        ->and($session->event_count)->toBe(2)
        // Enrichment (device-detector) runs on session start.
        ->and($session->browser)->toBe('Firefox')
        ->and($session->subject_id)->toBe(7)
        // Reconstructed event times sit ~1s before the server-set started_at;
        // last_activity_at is clamped forward so it never precedes the start.
        ->and($session->last_activity_at->toDateTimeString())->toBe('2026-07-01 10:00:00');

    expect(Event::count())->toBe(2);
});

it('reuses the open session within the timeout and accumulates counters', function () {
    $now = CarbonImmutable::parse('2026-07-01 10:00:00');
    CarbonImmutable::setTestNow($now);
    $action = app(IngestEventsAction::class);

    $action->execute('u-1', null, actionSnapshot(), new IncomingBatch(events: [incomingEvent(EventType::Pageview, $now)]));

    CarbonImmutable::setTestNow($now->addMinutes(2));
    $action->execute('u-1', null, actionSnapshot(), new IncomingBatch(events: [incomingEvent(EventType::Click, CarbonImmutable::now(), 'x')]));

    expect(Session::count())->toBe(1)
        ->and(Visitor::firstOrFail()->session_count)->toBe(1);

    $session = Session::firstOrFail();
    expect($session->pageview_count)->toBe(1)
        ->and($session->event_count)->toBe(2);
});

it('starts a new session after the timeout', function () {
    $now = CarbonImmutable::parse('2026-07-01 10:00:00');
    CarbonImmutable::setTestNow($now);
    $action = app(IngestEventsAction::class);

    $action->execute('u-1', null, actionSnapshot(), new IncomingBatch(events: [incomingEvent(EventType::Pageview, $now)]));

    CarbonImmutable::setTestNow($now->addMinutes(10));
    $action->execute('u-1', null, actionSnapshot(), new IncomingBatch(events: [incomingEvent(EventType::Pageview, CarbonImmutable::now())]));

    expect(Session::count())->toBe(2)
        ->and(Visitor::firstOrFail()->session_count)->toBe(2)
        // The identical URL still counts in the fresh session: the reload guard
        // starts from a null last URL after a timeout.
        ->and(Session::orderByDesc('id')->first()->pageview_count)->toBe(1);
});

it('collapses a reload of the same page within a session', function () {
    $now = CarbonImmutable::parse('2026-07-01 10:00:00');
    CarbonImmutable::setTestNow($now);
    $action = app(IngestEventsAction::class);

    $action->execute('u-1', null, actionSnapshot(), new IncomingBatch(events: [incomingEvent(EventType::Pageview, $now, url: 'https://vantadrive.ch/a')]));

    CarbonImmutable::setTestNow($now->addMinute());
    $action->execute('u-1', null, actionSnapshot(), new IncomingBatch(events: [incomingEvent(EventType::Pageview, CarbonImmutable::now(), url: 'https://vantadrive.ch/a')]));

    $session = Session::firstOrFail();
    expect(Session::count())->toBe(1)
        ->and($session->pageview_count)->toBe(1) // the reload is not a new view
        ->and($session->last_pageview_url)->toBe('https://vantadrive.ch/a');
});

it('counts real navigations including returning to a page (A to B back to A is 3)', function () {
    $now = CarbonImmutable::parse('2026-07-01 10:00:00');
    CarbonImmutable::setTestNow($now);

    app(IngestEventsAction::class)->execute('u-1', null, actionSnapshot(), new IncomingBatch(events: [
        incomingEvent(EventType::Pageview, $now->subSeconds(3), url: 'https://vantadrive.ch/a'),
        incomingEvent(EventType::Pageview, $now->subSeconds(2), url: 'https://vantadrive.ch/b'),
        incomingEvent(EventType::Pageview, $now->subSecond(), url: 'https://vantadrive.ch/a'),
    ]));

    expect(Session::firstOrFail()->pageview_count)->toBe(3)
        ->and(Event::count())->toBe(3);
});

it('bumps activity for a heartbeat without storing or counting it', function () {
    $now = CarbonImmutable::parse('2026-07-01 10:00:00');
    CarbonImmutable::setTestNow($now);
    $action = app(IngestEventsAction::class);

    $action->execute('u-1', null, actionSnapshot(), new IncomingBatch(events: [incomingEvent(EventType::Pageview, $now)]));

    CarbonImmutable::setTestNow($now->addMinutes(1));
    $action->execute('u-1', null, actionSnapshot(), new IncomingBatch(events: [incomingEvent(EventType::Heartbeat, CarbonImmutable::now())]));

    expect(Event::count())->toBe(1);

    $session = Session::firstOrFail();
    expect($session->event_count)->toBe(1)
        ->and($session->last_activity_at->toDateTimeString())->toBe('2026-07-01 10:01:00');
});
