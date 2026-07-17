<?php

use Carbon\CarbonImmutable;
use Falcon\Analytics\Actions\ForgetVisitorAction;
use Falcon\Analytics\Actions\IngestEventsAction;
use Falcon\Analytics\Actions\MergeVisitorsAction;
use Falcon\Analytics\DTOs\IncomingBatch;
use Falcon\Analytics\DTOs\IncomingEvent;
use Falcon\Analytics\DTOs\RequestSnapshot;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Tests\Fixtures\Models\TestAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function identityVisitor(string $uuid, ?array $subject = null, string $firstSeen = '2026-07-01 10:00:00'): Visitor
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

function identitySession(Visitor $visitor, string $startedAt, ?array $subject = null, bool $withEvent = false): Session
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

function identityIngest(string $uuid, ?array $subject): void
{
    app(IngestEventsAction::class)->execute($uuid, $subject, new RequestSnapshot(
        ip: '85.4.12.66',
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        host: 'vantadrive.test',
    ), new IncomingBatch(events: [
        new IncomingEvent(type: EventType::Pageview, occurredAt: CarbonImmutable::now(), url: 'https://vantadrive.test/'),
    ]));
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-07-10 12:00:00'));
});

// ── Fusion à l'identification ───────────────────────

it('folds a newly identified browser into the person existing profile', function () {
    $canonical = identityVisitor('uuid-pc', ['type' => 'client', 'id' => 7], '2026-07-01 10:00:00');
    identitySession($canonical, '2026-07-01 10:00:00', ['type' => 'client', 'id' => 7]);

    $phone = identityVisitor('uuid-phone', null, '2026-07-05 09:00:00');
    identitySession($phone, '2026-07-05 09:00:00', null, withEvent: true);
    identitySession($phone, '2026-07-06 18:00:00');

    identityIngest('uuid-phone', ['type' => 'client', 'id' => 7]);

    $phone->refresh();
    $canonical->refresh();

    expect($phone->merged_into_id)->toBe($canonical->id)
        ->and($phone->session_count)->toBe(0)
        ->and(Session::query()->where('visitor_id', $phone->id)->count())->toBe(0)
        ->and(Event::query()->where('visitor_id', $phone->id)->count())->toBe(0)
        ->and(Session::query()->where('visitor_id', $canonical->id)->count())->toBe(4)
        ->and($canonical->session_count)->toBe(4)
        ->and($canonical->first_seen_at->toDateTimeString())->toBe('2026-07-01 10:00:00');
});

it('keeps routing a folded uuid to the canonical profile', function () {
    $canonical = identityVisitor('uuid-pc', ['type' => 'client', 'id' => 7]);
    $phone = identityVisitor('uuid-phone');
    $phone->update(['merged_into_id' => $canonical->id]);

    identityIngest('uuid-phone', null);

    expect(Session::query()->where('visitor_id', $canonical->id)->count())->toBe(1)
        ->and(Session::query()->where('visitor_id', $phone->id)->count())->toBe(0);
});

it('keeps two simultaneously browsing devices in two separate sessions', function () {
    identityIngest('uuid-pc', ['type' => 'client', 'id' => 7]);
    identityIngest('uuid-phone', ['type' => 'client', 'id' => 7]);
    identityIngest('uuid-pc', ['type' => 'client', 'id' => 7]);

    $canonical = Visitor::query()->where('uuid', 'uuid-pc')->firstOrFail();
    $sessions = Session::query()->where('visitor_id', $canonical->id)->orderBy('id')->get();

    expect(Visitor::query()->where('uuid', 'uuid-phone')->value('merged_into_id'))->toBe($canonical->id)
        ->and($sessions)->toHaveCount(2)
        ->and($sessions->pluck('browser_key')->sort()->values()->all())->toBe(['uuid-pc', 'uuid-phone']);
});

// ── Navigateur partagé ──────────────────────────────

it('routes a login on a shared browser to the person own profile', function () {
    $shared = identityVisitor('uuid-shared', ['type' => 'client', 'id' => 7]);
    $own = identityVisitor('uuid-phone', ['type' => 'client', 'id' => 9]);

    identityIngest('uuid-shared', ['type' => 'client', 'id' => 9]);

    $session = Session::query()->latest('id')->firstOrFail();

    expect($session->visitor_id)->toBe($own->id)
        ->and($session->browser_key)->toBe('uuid-shared')
        ->and($shared->refresh()->subject_id)->toBe(7)
        ->and($shared->merged_into_id)->toBeNull();
});

it('leaves a shared-browser login on the browser when the person has no profile yet', function () {
    $shared = identityVisitor('uuid-shared', ['type' => 'client', 'id' => 7]);

    identityIngest('uuid-shared', ['type' => 'client', 'id' => 9]);

    $session = Session::query()->latest('id')->firstOrFail();

    expect($session->visitor_id)->toBe($shared->id)
        ->and($session->subject_id)->toBe(9)
        ->and($shared->refresh()->subject_id)->toBe(7);
});

it('relocates stray identified sessions when the person gets their own profile', function () {
    $shared = identityVisitor('uuid-shared', ['type' => 'client', 'id' => 7]);
    $stray = identitySession($shared, '2026-07-05 09:00:00', ['type' => 'client', 'id' => 9], withEvent: true);

    identityIngest('uuid-phone', ['type' => 'client', 'id' => 9]);

    $own = Visitor::query()->where('uuid', 'uuid-phone')->firstOrFail();

    expect($stray->refresh()->visitor_id)->toBe($own->id)
        ->and(Event::query()->where('session_id', $stray->id)->value('visitor_id'))->toBe($own->id)
        ->and($own->session_count)->toBe(2)
        ->and($shared->refresh()->session_count)->toBe(0);
});

// ── Consolidation (migration) ───────────────────────

it('consolidates pre-existing duplicate profiles into the oldest one', function () {
    $oldest = identityVisitor('uuid-a', ['type' => 'client', 'id' => 7], '2026-06-01 10:00:00');
    identitySession($oldest, '2026-06-01 10:00:00', ['type' => 'client', 'id' => 7]);

    $newer = identityVisitor('uuid-b', ['type' => 'client', 'id' => 7], '2026-06-10 10:00:00');
    identitySession($newer, '2026-06-10 10:00:00', ['type' => 'client', 'id' => 7], withEvent: true);
    identitySession($newer, '2026-06-11 10:00:00');

    $foreign = identityVisitor('uuid-c', ['type' => 'lessor', 'id' => 2], '2026-06-05 10:00:00');
    $strayOnForeign = identitySession($foreign, '2026-06-05 10:00:00', ['type' => 'client', 'id' => 7]);

    app(MergeVisitorsAction::class)->consolidateExisting();

    $oldest->refresh();

    expect($newer->refresh()->merged_into_id)->toBe($oldest->id)
        ->and($oldest->merged_into_id)->toBeNull()
        ->and(Session::query()->where('visitor_id', $oldest->id)->count())->toBe(4)
        ->and($oldest->session_count)->toBe(4)
        ->and($strayOnForeign->refresh()->visitor_id)->toBe($oldest->id)
        ->and($foreign->refresh()->merged_into_id)->toBeNull()
        ->and($foreign->session_count)->toBe(0);
});

it('widens the canonical seen window when merging', function () {
    $canonical = identityVisitor('uuid-a', ['type' => 'client', 'id' => 7], '2026-06-10 10:00:00');
    $alias = identityVisitor('uuid-b', ['type' => 'client', 'id' => 7], '2026-06-01 08:00:00');
    $alias->update(['last_seen_at' => CarbonImmutable::parse('2026-07-09 22:00:00')]);

    $merged = app(MergeVisitorsAction::class)->execute($alias, $canonical);

    expect($merged->first_seen_at->toDateTimeString())->toBe('2026-06-01 08:00:00')
        ->and($merged->last_seen_at->toDateTimeString())->toBe('2026-07-09 22:00:00');
});

// ── RGPD et navigation ──────────────────────────────

it('erases the merged aliases together with the canonical profile', function () {
    $canonical = identityVisitor('uuid-a', ['type' => 'client', 'id' => 7]);
    $alias = identityVisitor('uuid-b');
    $alias->update(['merged_into_id' => $canonical->id]);

    app(ForgetVisitorAction::class)->execute($canonical);

    expect(Visitor::query()->count())->toBe(0);
});

it('redirects the detail of a folded profile to the canonical one', function () {
    $canonical = identityVisitor('uuid-a', ['type' => 'client', 'id' => 7]);
    $alias = identityVisitor('uuid-b');
    $alias->update(['merged_into_id' => $canonical->id]);

    $this->actingAs(TestAdmin::create([]), 'admin')
        ->get(route('analytics.visitors.show', $alias))
        ->assertRedirect(route('analytics.visitors.show', $canonical));
});
