<?php

use Carbon\CarbonImmutable;
use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makeVisitor(): Visitor
{
    return Visitor::create([
        'uuid' => (string) Str::uuid(),
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);
}

function makeSession(Visitor $visitor): Session
{
    return Session::create([
        'visitor_id' => $visitor->id,
        'started_at' => now(),
        'last_activity_at' => now(),
    ]);
}

it('casts session attributes', function () {
    $session = makeSession(makeVisitor());
    $session->update(['latitude' => 46.2044, 'longitude' => 6.1432, 'is_bot' => false]);

    $fresh = $session->fresh();

    expect($fresh->started_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($fresh->ended_at)->toBeNull()
        ->and($fresh->is_bot)->toBeFalse()
        ->and($fresh->latitude)->toBe(46.2044)
        ->and($fresh->pageview_count)->toBe(0);
});

it('casts the event type to the enum, props to an array and value to float', function () {
    $visitor = makeVisitor();
    $event = Event::create([
        'session_id' => makeSession($visitor)->id,
        'visitor_id' => $visitor->id,
        'occurred_at' => now(),
        'type' => EventType::Click,
        'name' => 'listing.contact_click',
        'props' => ['listing_id' => 42],
        'value' => 3,
    ]);

    $fresh = $event->fresh();

    expect($fresh->type)->toBe(EventType::Click)
        ->and($fresh->props)->toBe(['listing_id' => 42])
        ->and($fresh->occurred_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($fresh->value)->toBe(3.0);
});

it('wires the relationships in both directions', function () {
    $visitor = makeVisitor();
    $session = makeSession($visitor);
    $event = Event::create([
        'session_id' => $session->id,
        'visitor_id' => $visitor->id,
        'occurred_at' => now(),
        'type' => EventType::Pageview,
    ]);

    expect($session->visitor->is($visitor))->toBeTrue()
        ->and($visitor->sessions->first()->is($session))->toBeTrue()
        ->and($event->session->is($session))->toBeTrue()
        ->and($event->visitor->is($visitor))->toBeTrue()
        ->and($visitor->events->first()->is($event))->toBeTrue();
});

it('disables timestamps on every model', function () {
    expect((new Visitor)->usesTimestamps())->toBeFalse()
        ->and((new Session)->usesTimestamps())->toBeFalse()
        ->and((new Event)->usesTimestamps())->toBeFalse();
});
