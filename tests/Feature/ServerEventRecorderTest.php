<?php

use Falcon\Analytics\Enums\EventType;
use Falcon\Analytics\Facades\Analytics;
use Falcon\Analytics\Models\Event;
use Falcon\Analytics\Models\Session;
use Falcon\Analytics\Models\Visitor;
use Falcon\Analytics\Services\ServerEventRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::get('/_analytics_record_test', function (ServerEventRecorder $recorder) {
        $recorder->record('Lead', value: 3.0, props: ['listing_id' => 42]);

        return response()->noContent();
    })->middleware('web');
});

it('records a server event for the current visitor', function () {
    $this->withoutDefer()->get('/_analytics_record_test')->assertNoContent();

    expect(Visitor::count())->toBe(1)
        ->and(Session::count())->toBe(1);

    $event = Event::firstOrFail();
    expect($event->type)->toBe(EventType::Custom)
        ->and($event->name)->toBe('Lead')
        ->and($event->value)->toBe(3.0)
        ->and($event->props)->toBe(['listing_id' => 42]);
});

it('does not record when tracking is disabled', function () {
    config(['analytics.enabled' => false]);

    $this->withoutDefer()->get('/_analytics_record_test')->assertNoContent();

    expect(Event::count())->toBe(0);
});

it('does not record for an excluded context', function () {
    Analytics::excludeUsing(fn () => true);

    $this->withoutDefer()->get('/_analytics_record_test')->assertNoContent();

    expect(Event::count())->toBe(0);
});

it('never throws to the caller even without a usable request context', function () {
    // Called outside an HTTP request: no session is available, resolution fails,
    // but the caller must never see an exception.
    expect(fn () => app(ServerEventRecorder::class)->record('X', value: 1.0))->not->toThrow(Throwable::class);

    expect(Event::count())->toBe(0);
});

it('is callable through the Analytics facade', function () {
    Route::get('/_analytics_record_facade', function () {
        Analytics::record('CompleteRegistration', value: 5.0);

        return response()->noContent();
    })->middleware('web');

    $this->withoutDefer()->get('/_analytics_record_facade')->assertNoContent();

    $event = Event::firstOrFail();
    expect($event->name)->toBe('CompleteRegistration')
        ->and($event->value)->toBe(5.0)
        ->and($event->type)->toBe(EventType::Custom);
});
